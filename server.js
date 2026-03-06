const express = require("express");
const cors = require("cors");
const bcrypt = require("bcrypt");
const jwt = require("jsonwebtoken");
const crypto = require("crypto");
const nodemailer = require("nodemailer");
const cookieParser = require("cookie-parser");
const helmet = require("helmet");
const rateLimit = require("express-rate-limit");
const { createClient } = require("@supabase/supabase-js");
const path = require("path");
const fs = require("fs");
const dotenv = require("dotenv");

const envPrimary = path.join(__dirname, ".env");
const envLegacy = path.join(__dirname, "Backend", ".env");
dotenv.config({ path: envPrimary });
if ((!process.env.SUPABASE_URL || !process.env.SUPABASE_SERVICE_ROLE_KEY) && fs.existsSync(envLegacy)) {
  dotenv.config({ path: envLegacy, override: false });
}

const app = express();
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

      if (!origin || allowed.length === 0 || allowed.includes(origin)) {
        return callback(null, true);
      }
      return callback(new Error("CORS not allowed"));
    },
    credentials: true
  })
);
app.use(helmet());
app.use(express.json());
app.use(cookieParser());

if (!process.env.JWT_SECRET) {
  console.warn("JWT_SECRET is not set. Tokens will not be secure.");
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

function isValidEmail(email) {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(email || ""));
}

function isStrongPassword(password) {
  const value = String(password || "");
  return value.length >= 8 && /[A-Za-z]/.test(value) && /[0-9]/.test(value);
}

function normalizeRole(role) {
  const value = String(role || "").trim().toLowerCase();
  if (value === "customer") return "client";
  return value;
}

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

function normalizePhone(phone) {
  return String(phone || "").trim();
}

function isValidKenyanPhone(phone) {
  return /^07[0-9]{8,9}$/.test(normalizePhone(phone));
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

const supabaseUrl = process.env.SUPABASE_URL;
const supabaseServiceRoleKey = process.env.SUPABASE_SERVICE_ROLE_KEY;
const supabase =
  supabaseUrl && supabaseServiceRoleKey
    ? createClient(supabaseUrl, supabaseServiceRoleKey, {
        auth: { autoRefreshToken: false, persistSession: false }
      })
    : null;

if (!supabase) {
  console.warn("Supabase is not configured. Set SUPABASE_URL and SUPABASE_SERVICE_ROLE_KEY.");
}

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
    return res.status(500).json({
      status: "error",
      db: "down",
      error: String(error.message || "Unknown database error")
    });
  }
  return res.json({ status: "ok", db: "up" });
});

// Signup API
app.post("/api/signup", async (req, res) => {
  if (!ensureSupabaseConfigured(res)) return;

  const clean = (v) => (typeof v === "string" ? v.trim() : v);
  const username = clean(req.body.username);
  const email = String(clean(req.body.email) || "").toLowerCase();
  const password = clean(req.body.password);
  const role = normalizeRole(clean(req.body.role));
  let service = clean(req.body.service);
  let phone = clean(req.body.phone);
  let location = clean(req.body.location);
  let about = clean(req.body.about);

  if (!username || !email || !password || !role) {
    return res.status(400).json({ message: "All required fields must be filled!" });
  }
  if (!isValidEmail(email)) {
    return res.status(400).json({ message: "Invalid email address" });
  }
  if (!isStrongPassword(password)) {
    return res.status(400).json({
      message: "Password must be at least 8 characters and include letters and numbers"
    });
  }

  if (role === "provider" && (!service || service.trim() === "")) {
    return res.status(400).json({ message: "Service is required for providers!" });
  }
  if (role !== "provider") {
    service = null;
    phone = null;
    location = null;
    about = null;
  }
  if (!["client", "provider", "admin"].includes(role)) {
    return res.status(400).json({ message: "Invalid role. Use customer/client, provider, or admin." });
  }

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

  const hashedPassword = await bcrypt.hash(password, 10);
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
app.post("/api/signin", authLimiter, async (req, res) => {
  if (!ensureSupabaseConfigured(res)) return;

  const clean = (v) => (typeof v === "string" ? v.trim() : v);
  const email = String(clean(req.body.email) || "").toLowerCase();
  const password = clean(req.body.password);

  if (!email || !password) {
    return res.status(400).json({ message: "Email and password are required" });
  }
  if (!isValidEmail(email)) {
    return res.status(400).json({ message: "Invalid email address" });
  }

  const { data: user, error: userError } = await supabase
    .from("users")
    .select("id, username, email, password, role")
    .eq("email", email)
    .maybeSingle();

  if (userError) {
    return res.status(500).json({ message: "Database error" });
  }
  if (!user) {
    return res.status(404).json({ message: "Account not found. Please sign up first." });
  }

  const match = await bcrypt.compare(password, user.password);
  if (!match) {
    return res.status(401).json({ message: "Invalid email or password" });
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

// Request password reset
app.post("/api/forgot-password", authLimiter, async (req, res) => {
  if (!ensureSupabaseConfigured(res)) return;

  const clean = (v) => (typeof v === "string" ? v.trim() : v);
  const email = String(clean(req.body.email) || "").toLowerCase();

  if (!email) {
    return res.status(400).json({ message: "Email is required" });
  }
  if (!isValidEmail(email)) {
    return res.status(400).json({ message: "Invalid email address" });
  }

  const { data: user, error: userError } = await supabase
    .from("users")
    .select("id, email")
    .eq("email", email)
    .maybeSingle();

  if (userError) return res.status(500).json({ message: "Database error" });

  if (!user) {
    // Always respond success to avoid account enumeration
    return res.json({ message: "If the email exists, a reset link has been sent." });
  }

  const transport = getSmtpTransport();
  if (!transport) {
    return res.status(500).json({ message: "Email service not configured" });
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
    return res.status(500).json({ message: "Failed to send reset email" });
  }

  return res.json({ message: "If the email exists, a reset link has been sent." });
});

// Reset password
app.post("/api/reset-password", authLimiter, async (req, res) => {
  if (!ensureSupabaseConfigured(res)) return;

  const clean = (v) => (typeof v === "string" ? v.trim() : v);
  const token = clean(req.body.token);
  const password = clean(req.body.password);

  if (!token || !password) {
    return res.status(400).json({ message: "Token and new password are required" });
  }
  if (!isStrongPassword(password)) {
    return res.status(400).json({
      message: "Password must be at least 8 characters and include letters and numbers"
    });
  }

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
  const hashedPassword = await bcrypt.hash(password, 10);

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
app.post("/api/bookings", authRequired, async (req, res) => {
  if (!ensureSupabaseConfigured(res)) return;

  const clean = (v) => (typeof v === "string" ? v.trim() : v);
  const providerId = Number(clean(req.body.provider_id));
  const customerPhone = normalizePhone(clean(req.body.customer_phone));
  const notes = clean(req.body.notes) || null;
  const bookingFeeKes = Number(process.env.BOOKING_FEE_KES || 149);

  if (!Number.isFinite(providerId) || providerId <= 0) {
    return res.status(400).json({ message: "Invalid provider_id" });
  }
  if (!isValidKenyanPhone(customerPhone)) {
    return res.status(400).json({ message: "Invalid phone number. Use 07XXXXXXXXX format." });
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
});

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
app.patch("/api/bookings/:id/status", authRequired, async (req, res) => {
  if (!ensureSupabaseConfigured(res)) return;

  const bookingId = Number(req.params.id);
  const allowed = ["confirmed", "in_progress", "completed", "cancelled"];
  const status = String(req.body.status || "").trim();

  if (!Number.isFinite(bookingId) || bookingId <= 0) {
    return res.status(400).json({ message: "Invalid booking id" });
  }
  if (!allowed.includes(status)) {
    return res.status(400).json({ message: "Invalid status value" });
  }

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
app.patch("/api/bookings/:id/payment-status", authRequired, adminOnly, async (req, res) => {
  if (!ensureSupabaseConfigured(res)) return;

  const bookingId = Number(req.params.id);
  const paymentStatus = String(req.body.payment_status || "").trim();
  const allowedStatuses = ["initiated", "pending", "success", "failed", "cancelled"];

  if (!Number.isFinite(bookingId) || bookingId <= 0) {
    return res.status(400).json({ message: "Invalid booking id" });
  }
  if (!allowedStatuses.includes(paymentStatus)) {
    return res.status(400).json({ message: "Invalid payment_status value" });
  }

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

// Admin: list users (excluding passwords)
app.get("/api/admin/users", authRequired, adminOnly, async (req, res) => {
  if (!ensureSupabaseConfigured(res)) return;

  const search = (req.query.search || "").trim();
  let query = supabase
    .from("users")
    .select("id, username, email, role, service, phone, location, about")
    .order("id", { ascending: false });

  if (search) {
    const safeSearch = search.replace(/[^a-zA-Z0-9@\s.\-]/g, "");
    query = query.or(
      `username.ilike.%${safeSearch}%,email.ilike.%${safeSearch}%,role.ilike.%${safeSearch}%,service.ilike.%${safeSearch}%,location.ilike.%${safeSearch}%`
    );
  }

  const { data, error } = await query;
  if (error) return res.status(500).json({ message: "Database error" });
  return res.json(data || []);
});

// Get providers (optional search via ?search=)
app.get("/api/users", async (req, res) => {
  if (!ensureSupabaseConfigured(res)) return;

  const search = (req.query.search || "").trim();
  let query = supabase
    .from("users")
    .select("id, username, role, service, phone, location, about")
    .eq("role", "provider")
    .not("service", "is", null)
    .neq("service", "");

  if (search) {
    const safeSearch = search.replace(/[^a-zA-Z0-9@\s.\-]/g, "");
    query = query.or(`username.ilike.%${safeSearch}%,service.ilike.%${safeSearch}%,location.ilike.%${safeSearch}%`);
  }

  const { data, error } = await query;
  if (error) return res.status(500).json({ message: "Database error" });
  return res.json(data || []);
});

// Start server
const PORT = process.env.PORT || 5000;
app.listen(PORT, () => {
  console.log(`Server running on http://localhost:${PORT}`);
});
