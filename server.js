const express = require("express");
const cors = require("cors");
const bcrypt = require("bcrypt");
const jwt = require("jsonwebtoken");
const crypto = require("crypto");
const nodemailer = require("nodemailer");
const cookieParser = require("cookie-parser");
const helmet = require("helmet");
const rateLimit = require("express-rate-limit");
const path = require("path");
const dotenv = require("dotenv");
const {
  schemas,
  validateBody,
  validateQuery,
  validateParams
} = require("./validation");

// __dirname (not cwd) so this finds .env next to server.js regardless of
// where the process was actually launched from.
dotenv.config({ path: path.join(__dirname, ".env") });

const app = express();

// Only trust X-Forwarded-For when actually deployed behind a real proxy/load
// balancer (set TRUST_PROXY=1 in that environment). Trusting it blindly in
// an environment with no proxy in front lets clients spoof their IP and
// bypass rate limiting.
if (String(process.env.TRUST_PROXY || "").trim() === "1") {
  app.set("trust proxy", 1);
}

app.use(
  cors({
    origin: (origin, callback) => {
      const raw = process.env.CORS_ORIGINS || process.env.FRONTEND_BASE_URL || "";
      const allowed = raw
        .split(",")
        .map((v) => v.trim())
        .filter(Boolean)
        .map((value) => {
          if (!value.includes("://")) return value;
          try {
            return new URL(value).origin;
          } catch (err) {
            return value;
          }
        });

      // Requests with no Origin header (same-origin, curl, server-to-server)
      // are always fine. Cross-origin requests need an explicit match —
      // treating "nothing configured" as "allow everything" would mean any
      // website could make credentialed (cookie-bearing) requests here the
      // moment someone forgot to set CORS_ORIGINS in production.
      if (!origin || allowed.includes(origin)) {
        return callback(null, true);
      }
      return callback(new Error("CORS not allowed"));
    },
    credentials: true
  })
);
app.use(helmet());
app.use(express.json({ limit: "100kb" }));
app.use(cookieParser());

// Serve the frontend from this same server. This is the actual fix for
// "backend doesn't communicate with frontend properly": before this, every
// HTML page had http://localhost:5000 hardcoded in 8 different places, so
// anything other than "both running locally on the exact default port"
// silently broke. With the frontend served from here, every page can use a
// same-origin relative URL instead — there's no longer a URL to get wrong.
//
// Frontend files live in ./public. Locally this line is what serves them.
// On Vercel, express.static() is ignored entirely and Vercel's CDN serves
// everything in ./public directly instead — so this path has to match
// wherever the static files actually live either way.
app.use(
  express.static(path.join(__dirname, "public"), {
    // Don't accidentally serve source/config files as if they were assets
    index: "index.html",
    extensions: false,
    dotfiles: "ignore"
  })
);

// Every protected route depends on this. Warning-and-continuing meant the
// app would boot "successfully" and then fail unpredictably on first
// login — better to refuse to start than to silently run with no auth
// security at all.
if (!process.env.JWT_SECRET || process.env.JWT_SECRET.length < 16) {
  console.error("FATAL: JWT_SECRET is missing or too short (need 16+ chars). Refusing to start.");
  process.exit(1);
}

const defaultLimiter = rateLimit({
  windowMs: 15 * 60 * 1000,
  max: 200,
  standardHeaders: true,
  legacyHeaders: false
});
app.use(defaultLimiter);

const authLimiter = rateLimit({
  windowMs: 15 * 60 * 1000,
  max: 20,
  standardHeaders: true,
  legacyHeaders: false,
  message: { message: "Too many attempts. Please try again later." }
});

// Signup wasn't rate-limited at all before — wide open to automated
// account-creation spam. Capped tighter than login since account creation
// is a rarer, more expensive action than signing in.
const signupLimiter = rateLimit({
  windowMs: 60 * 60 * 1000,
  max: 10,
  standardHeaders: true,
  legacyHeaders: false,
  message: { message: "Too many accounts created from this network. Please try again later." }
});

// Prevents booking-spam against providers (and against your own booking_payments table).
const bookingLimiter = rateLimit({
  windowMs: 60 * 60 * 1000,
  max: 30,
  standardHeaders: true,
  legacyHeaders: false,
  message: { message: "Too many booking attempts. Please try again later." }
});

// A 4-digit PIN only has 10,000 possible values, so it needs a much tighter
// cap than the regular authLimiter (which allows 20/15min) — 5/15min still
// lets a persistent attacker grind through the whole space eventually from
// a single IP, but that's the inherent ceiling of a shared 4-digit PIN, not
// something a rate limiter alone can fully close. Consider pairing this
// with IP allowlisting at the network/CDN level if this panel is a real
// target.
const adminPinLimiter = rateLimit({
  windowMs: 15 * 60 * 1000,
  max: 5,
  standardHeaders: true,
  legacyHeaders: false,
  message: { message: "Too many attempts. Please try again later." }
});

// Field-level validation now lives in validation.js (zod schemas applied as
// route middleware). bcrypt cost factor is configurable but defaults to the
// current OWASP-recommended minimum.
const SALT_ROUNDS = Number(process.env.BCRYPT_ROUNDS) || 12;

// Used only to give the "no such user" path on signin the same bcrypt.compare
// delay as the "wrong password" path — otherwise the two cases are
// distinguishable by response time even with an identical message/status.
const DUMMY_PASSWORD_HASH = "$2b$12$q1XjBmscOlGwDjxVxKikJu0PMdY5b/gqbySdcDPWOh24CBJ1TBqX6";

function getAuthToken(req) {
  const header = req.headers.authorization || "";
  if (header.startsWith("Bearer ")) {
    return header.slice(7);
  }
  if (req.cookies && req.cookies.juakazi_token) {
    return req.cookies.juakazi_token;
  }
  return null;
}

function getSmtpTransport() {
  const host = process.env.SMTP_HOST;
  const port = Number(process.env.SMTP_PORT || 587);
  const user = process.env.SMTP_USER;
  const pass = process.env.SMTP_PASS;
  const secure = String(process.env.SMTP_SECURE || "").toLowerCase() === "true";

  if (!host || !user || !pass) {
    return null;
  }

  return nodemailer.createTransport({
    host,
    port,
    secure,
    auth: { user, pass }
  });
}

function getFrontendBaseUrl() {
  return process.env.FRONTEND_BASE_URL || "http://localhost/Juakazi";
}

function authRequired(req, res, next) {
  const token = getAuthToken(req);
  if (!token) {
    return res.status(401).json({ message: "Missing token" });
  }
  try {
    const decoded = jwt.verify(token, process.env.JWT_SECRET);
    req.user = decoded;
    return next();
  } catch (err) {
    return res.status(401).json({ message: "Invalid or expired token" });
  }
}

function adminOnly(req, res, next) {
  if (req.user && req.user.role === "admin") return next();
  return res.status(403).json({ message: "Admin access required" });
}

// Plain === on the PIN would leak how many leading digits matched through
// response-time differences. This is a small precaution — the rate limiter
// above is what actually keeps a 4-digit space from being brute-forced.
function pinMatches(submitted, configured) {
  const a = Buffer.from(String(submitted));
  const b = Buffer.from(String(configured));
  if (a.length !== b.length) {
    crypto.timingSafeEqual(a, a); // keep timing consistent with the equal-length path
    return false;
  }
  return crypto.timingSafeEqual(a, b);
}

// Single source of truth for the Supabase client lives in db.js now —
// this used to duplicate that entire block inline.
const supabase = require("./db");

function ensureSupabaseConfigured(res) {
  if (!supabase) {
    res.status(500).json({ message: "Database is not configured" });
    return false;
  }
  return true;
}

// Health check
app.get("/health", async (req, res) => {
  if (!ensureSupabaseConfigured(res)) return;
  const { error } = await supabase.from("users").select("id").limit(1);
  if (error) {
    console.error("Health check DB error:", error.message);
    // Deliberately not returning error.message here — this endpoint is
    // typically unauthenticated, so internal DB error text shouldn't be
    // handed to whoever's polling it.
    return res.status(500).json({ status: "error", db: "down" });
  }
  return res.json({ status: "ok", db: "up" });
});

// Public config the frontend needs to render correctly instead of guessing
// or hardcoding it. BOOKING_FEE_KES is the actual source of truth used when
// a booking is created (see POST /api/bookings below) — without this route,
// the services page had "KSh 149" typed directly into the HTML, so changing
// the env var would silently make the displayed price wrong.
app.get("/api/config", (req, res) => {
  return res.json({
    booking_fee_kes: Number(process.env.BOOKING_FEE_KES || 149)
  });
});

// Signup API
app.post("/api/signup", signupLimiter, validateBody(schemas.signupSchema), async (req, res) => {
  if (!ensureSupabaseConfigured(res)) return;

  // req.body is now the zod-parsed, trimmed, type-checked payload.
  const { username, email, password, role } = req.body;
  const service = role === "provider" ? req.body.service : null;
  const phone = role === "provider" ? req.body.phone : null;
  const location = role === "provider" ? req.body.location || null : null;
  const about = role === "provider" ? req.body.about || null : null;

  const { data: existingUser, error: existingUserError } = await supabase
    .from("users")
    .select("id")
    .eq("email", email)
    .maybeSingle();

  if (existingUserError) {
    return res.status(500).json({ message: "Database error" });
  }
  if (existingUser) {
    return res.status(400).json({ message: "Email already exists!" });
  }

  const hashedPassword = await bcrypt.hash(password, SALT_ROUNDS);
  const { data: insertedUser, error: insertError } = await supabase
    .from("users")
    .insert({
      username,
      email,
      password: hashedPassword,
      role,
      service,
      phone,
      location,
      about
    })
    .select("id, username, email, role, service, phone, location, about")
    .single();

  if (insertError) {
    return res.status(500).json({
      message: "Error inserting user",
      error: String(insertError.message || "Unknown database error")
    });
  }

  return res.status(201).json({ message: "Account created successfully", user: insertedUser });
});

// Signin API
app.post("/api/signin", authLimiter, validateBody(schemas.signinSchema), async (req, res) => {
  if (!ensureSupabaseConfigured(res)) return;

  const { email, password } = req.body;

  const { data: user, error: userError } = await supabase
    .from("users")
    .select("id, username, email, password, role")
    .eq("email", email)
    .maybeSingle();

  if (userError) {
    return res.status(500).json({ message: "Database error" });
  }
  // Same status/message whether the email doesn't exist or the password is
  // wrong — previously "Account not found" (404) vs "Invalid email or
  // password" (401) let anyone check which emails have accounts here just
  // by watching the status code, the same class of leak already fixed on
  // forgot-password.
  const invalidCredentials = () => res.status(401).json({ message: "Invalid email or password" });

  if (!user) {
    await bcrypt.compare(password, DUMMY_PASSWORD_HASH);
    return invalidCredentials();
  }

  const match = await bcrypt.compare(password, user.password);
  if (!match) {
    return invalidCredentials();
  }

  const token = jwt.sign(
    { id: user.id, email: user.email, role: user.role, username: user.username },
    process.env.JWT_SECRET,
    { expiresIn: "7d" }
  );

  const isProd = String(process.env.NODE_ENV || "").toLowerCase() === "production";
  res.cookie("juakazi_token", token, {
    httpOnly: true,
    secure: isProd,
    sameSite: "lax",
    maxAge: 7 * 24 * 60 * 60 * 1000
  });

  return res.json({
    message: "Login successful",
    user: { id: user.id, username: user.username, email: user.email, role: user.role }
  });
});

// Admin login — a single shared PIN (set as ADMIN_PIN) instead of an
// individual admin account's email/password. There's no per-admin identity
// behind this, so the token uses a fixed synthetic user; every existing
// adminOnly route only checks req.user.role, so this is a drop-in fit.
// Doesn't touch Supabase at all, so admin login still works even if the
// database is having issues.
app.post("/api/admin/login", adminPinLimiter, validateBody(schemas.adminPinLoginSchema), (req, res) => {
  const configuredPin = process.env.ADMIN_PIN;
  if (!configuredPin || !/^\d{4}$/.test(configuredPin)) {
    console.error("admin login: ADMIN_PIN is not set (or isn't exactly 4 digits)");
    return res.status(500).json({ message: "Admin login is not configured" });
  }

  const { pin } = req.body;
  if (!pinMatches(pin, configuredPin)) {
    return res.status(401).json({ message: "Incorrect PIN" });
  }

  const token = jwt.sign(
    { id: 0, email: null, role: "admin", username: "admin" },
    process.env.JWT_SECRET,
    { expiresIn: "12h" } // shorter than the regular 7d session — a shared PIN is weaker, so the window it's useful in is smaller too
  );

  const isProd = String(process.env.NODE_ENV || "").toLowerCase() === "production";
  res.cookie("juakazi_token", token, {
    httpOnly: true,
    secure: isProd,
    sameSite: "lax",
    maxAge: 12 * 60 * 60 * 1000
  });

  return res.json({
    message: "Admin login successful",
    user: { id: 0, username: "admin", role: "admin" }
  });
});

// Request password reset
app.post("/api/forgot-password", authLimiter, validateBody(schemas.forgotPasswordSchema), async (req, res) => {
  if (!ensureSupabaseConfigured(res)) return;

  const { email } = req.body;
  const genericResponse = { message: "If the email exists, a reset link has been sent." };

  const { data: user, error: userError } = await supabase
    .from("users")
    .select("id, email")
    .eq("email", email)
    .maybeSingle();

  if (userError) return res.status(500).json({ message: "Database error" });

  if (!user) {
    // Always respond success to avoid account enumeration
    return res.json(genericResponse);
  }

  const transport = getSmtpTransport();
  if (!transport) {
    // Previously returned 500 here, which — combined with the 200 for an
    // unknown email above — let an attacker tell real accounts from fake
    // ones just by the status code. Log it server-side, respond the same
    // generic way either way.
    console.error("forgot-password: SMTP transport not configured");
    return res.json(genericResponse);
  }

  const token = crypto.randomBytes(32).toString("hex");
  const tokenHash = crypto.createHash("sha256").update(token).digest("hex");
  const expiresAt = new Date(Date.now() + 60 * 60 * 1000).toISOString(); // 1 hour

  const { error: deleteResetError } = await supabase
    .from("password_resets")
    .delete()
    .eq("user_id", user.id);
  if (deleteResetError) return res.status(500).json({ message: "Database error" });

  const { error: insertResetError } = await supabase.from("password_resets").insert({
    user_id: user.id,
    token_hash: tokenHash,
    expires_at: expiresAt
  });
  if (insertResetError) return res.status(500).json({ message: "Database error" });

  const resetUrl = `${getFrontendBaseUrl()}/reset_password.html?token=${token}`;
  try {
    await transport.sendMail({
      from: process.env.SMTP_FROM || process.env.SMTP_USER,
      to: user.email,
      subject: "Reset your Juakazi password",
      text:
        "You requested a password reset. Use the link below to set a new password:\n\n" +
        resetUrl +
        "\n\nIf you did not request this, you can ignore this email.",
      html:
        "<p>You requested a password reset. Use the link below to set a new password:</p>" +
        `<p><a href="${resetUrl}">${resetUrl}</a></p>` +
        "<p>If you did not request this, you can ignore this email.</p>"
    });
  } catch (err) {
    // Same reasoning as above — don't let email-send failures leak account existence.
    console.error("forgot-password: failed to send reset email:", err.message);
    return res.json(genericResponse);
  }

  return res.json(genericResponse);
});

// Reset password
app.post("/api/reset-password", authLimiter, validateBody(schemas.resetPasswordSchema), async (req, res) => {
  if (!ensureSupabaseConfigured(res)) return;

  const { token, password } = req.body;
  const tokenHash = crypto.createHash("sha256").update(token).digest("hex");
  const nowIso = new Date().toISOString();
  const { data: resetRow, error: resetLookupError } = await supabase
    .from("password_resets")
    .select("user_id")
    .eq("token_hash", tokenHash)
    .gt("expires_at", nowIso)
    .order("expires_at", { ascending: false })
    .limit(1)
    .maybeSingle();

  if (resetLookupError) return res.status(500).json({ message: "Database error" });
  if (!resetRow) {
    return res.status(400).json({ message: "Invalid or expired reset token" });
  }

  const userId = resetRow.user_id;
  const hashedPassword = await bcrypt.hash(password, SALT_ROUNDS);

  const { error: updateError } = await supabase
    .from("users")
    .update({ password: hashedPassword })
    .eq("id", userId);
  if (updateError) return res.status(500).json({ message: "Database error" });

  const { error: cleanupError } = await supabase.from("password_resets").delete().eq("user_id", userId);
  if (cleanupError) return res.status(500).json({ message: "Database error" });

  return res.json({ message: "Password reset successful. You can now sign in." });
});

// Protected route example
app.get("/api/me", authRequired, (req, res) => {
  return res.json({ user: req.user });
});

app.post("/api/logout", (req, res) => {
  const isProd = String(process.env.NODE_ENV || "").toLowerCase() === "production";
  res.clearCookie("juakazi_token", {
    httpOnly: true,
    secure: isProd,
    sameSite: "lax"
  });
  return res.json({ message: "Logged out" });
});

// Create booking (client -> provider)
app.post(
  "/api/bookings",
  authRequired,
  bookingLimiter,
  validateBody(schemas.createBookingSchema),
  async (req, res) => {
    if (!ensureSupabaseConfigured(res)) return;

    // Only clients (and admins acting on their behalf) create bookings.
    // Providers booking through their own account was never an intended
    // flow and had no server-side guard against it before.
    if (!["client", "admin"].includes(req.user.role)) {
      return res.status(403).json({ message: "Only clients can create bookings" });
    }

    const { provider_id: providerId, customer_phone: customerPhone, notes } = req.body;
    const bookingFeeKes = Number(process.env.BOOKING_FEE_KES || 149);

    if (providerId === req.user.id) {
      return res.status(400).json({ message: "You cannot book yourself" });
    }

    const { data: provider, error: providerLookupError } = await supabase
      .from("users")
      .select("id, role, service")
      .eq("id", providerId)
      .maybeSingle();

    if (providerLookupError) return res.status(500).json({ message: "Database error" });
    if (!provider || provider.role !== "provider") {
      return res.status(404).json({ message: "Provider not found" });
    }
    if (!provider.service || String(provider.service).trim() === "") {
      return res.status(400).json({ message: "Provider service is not configured" });
    }

    const { data: booking, error: bookingInsertError } = await supabase
      .from("bookings")
      .insert({
        provider_user_id: provider.id,
        client_user_id: req.user.id,
        service_name: provider.service,
        customer_phone: customerPhone,
        notes,
        booking_fee_kes: bookingFeeKes,
        status: "pending"
      })
      .select("id, provider_user_id, client_user_id, service_name, customer_phone, notes, booking_fee_kes, status, created_at")
      .single();

    if (bookingInsertError) return res.status(500).json({ message: "Failed to create booking" });

    const { data: payment, error: paymentInsertError } = await supabase
      .from("booking_payments")
      .insert({
        booking_id: booking.id,
        amount_kes: bookingFeeKes,
        currency: "KES",
        provider: "mpesa",
        payment_status: "pending",
        metadata: {
          source: "services.html",
          mode: "manual_or_future_stk_push"
        }
      })
      .select("id, booking_id, amount_kes, currency, provider, payment_status, created_at")
      .single();

    if (paymentInsertError) {
      return res.status(500).json({ message: "Booking created but payment init failed" });
    }

    return res.status(201).json({
      message: "Booking created. Proceed with payment confirmation flow.",
      booking,
      payment
    });
  }
);

// List bookings for logged-in provider/admin/client
app.get("/api/bookings/me", authRequired, async (req, res) => {
  if (!ensureSupabaseConfigured(res)) return;

  let query = supabase
    .from("bookings")
    .select(
      "id, provider_user_id, client_user_id, service_name, customer_phone, notes, booking_fee_kes, status, scheduled_for, created_at"
    )
    .order("id", { ascending: false });

  if (req.user.role === "provider") {
    query = query.eq("provider_user_id", req.user.id);
  } else if (req.user.role !== "admin") {
    query = query.eq("client_user_id", req.user.id);
  }

  const { data, error } = await query;
  if (error) return res.status(500).json({ message: "Database error" });
  return res.json(data || []);
});

// Provider/Admin booking status update
app.patch(
  "/api/bookings/:id/status",
  authRequired,
  validateParams(schemas.bookingIdParamSchema),
  validateBody(schemas.updateBookingStatusSchema),
  async (req, res) => {
  if (!ensureSupabaseConfigured(res)) return;

  const bookingId = req.validatedParams.id;
  const { status } = req.body;

  const { data: booking, error: bookingLookupError } = await supabase
    .from("bookings")
    .select("id, provider_user_id, client_user_id, status")
    .eq("id", bookingId)
    .maybeSingle();

  if (bookingLookupError) return res.status(500).json({ message: "Database error" });
  if (!booking) return res.status(404).json({ message: "Booking not found" });

  const isAdmin = req.user.role === "admin";
  const isProviderOwner = req.user.role === "provider" && booking.provider_user_id === req.user.id;
  const isClientOwner = booking.client_user_id === req.user.id;
  if (!isAdmin && !isProviderOwner && !(isClientOwner && status === "cancelled")) {
    return res.status(403).json({ message: "Not allowed to update this booking" });
  }

  const { data, error } = await supabase
    .from("bookings")
    .update({ status })
    .eq("id", bookingId)
    .select("id, status, updated_at")
    .single();

  if (error) return res.status(500).json({ message: "Failed to update booking status" });
  return res.json({ message: "Booking status updated", booking: data });
});

// Admin payment status update (for webhook/manual settlement)
app.patch(
  "/api/bookings/:id/payment-status",
  authRequired,
  adminOnly,
  validateParams(schemas.bookingIdParamSchema),
  validateBody(schemas.updatePaymentStatusSchema),
  async (req, res) => {
  if (!ensureSupabaseConfigured(res)) return;

  const bookingId = req.validatedParams.id;
  const { payment_status: paymentStatus } = req.body;

  const { data: payment, error: paymentLookupError } = await supabase
    .from("booking_payments")
    .select("id, booking_id, payment_status")
    .eq("booking_id", bookingId)
    .order("id", { ascending: false })
    .limit(1)
    .maybeSingle();

  if (paymentLookupError) return res.status(500).json({ message: "Database error" });
  if (!payment) return res.status(404).json({ message: "Payment record not found for this booking" });

  const paymentPatch = {
    payment_status: paymentStatus
  };
  if (paymentStatus === "success") {
    paymentPatch.paid_at = new Date().toISOString();
  }

  const { data: updatedPayment, error: paymentUpdateError } = await supabase
    .from("booking_payments")
    .update(paymentPatch)
    .eq("id", payment.id)
    .select("id, booking_id, payment_status, paid_at, updated_at")
    .single();

  if (paymentUpdateError) return res.status(500).json({ message: "Failed to update payment status" });

  if (paymentStatus === "success") {
    const { error: bookingUpdateError } = await supabase
      .from("bookings")
      .update({ status: "paid" })
      .eq("id", bookingId);
    if (bookingUpdateError) return res.status(500).json({ message: "Payment updated but booking status update failed" });
  }

  return res.json({
    message: "Payment status updated",
    payment: updatedPayment
  });
});

// Admin: list users (excluding passwords). Response shape is { users, total }
// rather than a bare array — admin-dashboard.html (the only consumer) needs
// an exact total for the "Total Customers" stat even when a search/limit
// narrows the returned page, which Supabase's { count: "exact" } gives us
// without a second round-trip.
app.get("/api/admin/users", authRequired, adminOnly, validateQuery(schemas.paginationQuery), async (req, res) => {
  if (!ensureSupabaseConfigured(res)) return;

  const { search, limit, offset } = req.validatedQuery;
  let query = supabase
    .from("users")
    .select("id, username, email, role, service, phone, location, about, created_at", { count: "exact" })
    .order("id", { ascending: false })
    .range(offset, offset + limit - 1);

  if (search) {
    // Length-capped by the schema above; this whitelist additionally strips
    // anything (commas, parens, %) that could break out of the raw .or()
    // filter string PostgREST expects here.
    const safeSearch = search.replace(/[^a-zA-Z0-9@\s.\-]/g, "");
    query = query.or(
      `username.ilike.%${safeSearch}%,email.ilike.%${safeSearch}%,role.ilike.%${safeSearch}%,service.ilike.%${safeSearch}%,location.ilike.%${safeSearch}%`
    );
  }

  const { data, error, count } = await query;
  if (error) return res.status(500).json({ message: "Database error" });
  return res.json({ users: data || [], total: typeof count === "number" ? count : (data || []).length });
});

// Get providers (optional search via ?search=, paginated). Powers the public
// services.html grid, so this only ever returns provider accounts, never
// clients/admins. rating/rating_count come from the users table (see
// add-provider-rating.sql) — they're nullable/zero until a real
// reviews-per-booking flow exists to populate them, which services.html
// handles by showing "New" for anyone with no rating yet.
app.get("/api/users", validateQuery(schemas.paginationQuery), async (req, res) => {
  if (!ensureSupabaseConfigured(res)) return;

  const { search, limit, offset } = req.validatedQuery;
  let query = supabase
    .from("users")
    .select("id, username, role, service, phone, location, about, rating, rating_count")
    .eq("role", "provider")
    .not("service", "is", null)
    .neq("service", "")
    .order("rating", { ascending: false, nullsFirst: false })
    .range(offset, offset + limit - 1);

  if (search) {
    const safeSearch = search.replace(/[^a-zA-Z0-9@\s.\-]/g, "");
    query = query.or(`username.ilike.%${safeSearch}%,service.ilike.%${safeSearch}%,location.ilike.%${safeSearch}%`);
  }

  const { data, error } = await query;
  if (error) return res.status(500).json({ message: "Database error" });
  return res.json(data || []);
});

// 404 for unmatched API routes
app.use("/api", (req, res) => {
  res.status(404).json({ message: "Not found" });
});

// Catch-all error handler. Without this, an uncaught error (like the CORS
// rejection above, or anything unexpected in a route) falls through to
// Express's default handler, which renders an HTML page with the full
// stack trace and server file paths — a real information leak, and not
// JSON, unlike every other response this API sends.
app.use((err, req, res, next) => { // eslint-disable-line no-unused-vars
  console.error("Unhandled error:", err);
  if (res.headersSent) return next(err);
  const isCorsRejection = String(err && err.message) === "CORS not allowed";
  res.status(isCorsRejection ? 403 : 500).json({
    message: isCorsRejection ? "Origin not allowed" : "Something went wrong"
  });
});

// Start server
const PORT = process.env.PORT || 5000;
app.listen(PORT, () => {
  console.log(`Server running on http://localhost:${PORT}`);
});
