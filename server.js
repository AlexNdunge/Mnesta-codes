const express = require("express");
const cors = require("cors");
const bcrypt = require("bcrypt");
const mysql = require("mysql2");
const jwt = require("jsonwebtoken");
const crypto = require("crypto");
const nodemailer = require("nodemailer");
const cookieParser = require("cookie-parser");
const helmet = require("helmet");
const rateLimit = require("express-rate-limit");
require("dotenv").config();

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

// MySQL Connection
const db = mysql.createConnection({
  host: process.env.DB_HOST,
  user: process.env.DB_USER,
  password: process.env.DB_PASSWORD,
  database: process.env.DB_NAME
});

db.connect((err) => {
  if (err) {
    console.log("MySQL Connection Failed:", err);
  } else {
    console.log("MySQL Connected Successfully");
  }
});

// Health check
app.get("/health", (req, res) => {
  db.ping((err) => {
    if (err) {
      return res.status(500).json({ status: "error", db: "down" });
    }
    return res.json({ status: "ok", db: "up" });
  });
});

// Signup API
app.post("/api/signup", async (req, res) => {
  const clean = (v) => (typeof v === "string" ? v.trim() : v);
  const username = clean(req.body.username);
  const email = clean(req.body.email);
  const password = clean(req.body.password);
  const role = clean(req.body.role);
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

  db.query("SELECT * FROM users WHERE email = ?", [email], async (err, results) => {
    if (err) return res.status(500).json({ message: "Database error" });

    if (results.length > 0) {
      return res.status(400).json({ message: "Email already exists!" });
    }

    const hashedPassword = await bcrypt.hash(password, 10);

    db.query(
      `INSERT INTO users (username, email, password, role, service, phone, location, about)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?)`,
      [username, email, hashedPassword, role, service, phone, location, about],
      (err) => {
        if (err) return res.status(500).json({ message: "Error inserting user" });

        return res.status(201).json({ message: "Account created successfully" });
      }
    );
  });
});

// Signin API
app.post("/api/signin", authLimiter, (req, res) => {
  const clean = (v) => (typeof v === "string" ? v.trim() : v);
  const email = clean(req.body.email);
  const password = clean(req.body.password);

  if (!email || !password) {
    return res.status(400).json({ message: "Email and password are required" });
  }
  if (!isValidEmail(email)) {
    return res.status(400).json({ message: "Invalid email address" });
  }

  db.query("SELECT id, username, email, password, role FROM users WHERE email = ?", [email], async (err, results) => {
    if (err) return res.status(500).json({ message: "Database error" });
    if (!results.length) {
      return res.status(404).json({ message: "Account not found. Please sign up first." });
    }

    const user = results[0];
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
});

// Request password reset
app.post("/api/forgot-password", authLimiter, (req, res) => {
  const clean = (v) => (typeof v === "string" ? v.trim() : v);
  const email = clean(req.body.email);

  if (!email) {
    return res.status(400).json({ message: "Email is required" });
  }
  if (!isValidEmail(email)) {
    return res.status(400).json({ message: "Invalid email address" });
  }

  db.query("SELECT id, email FROM users WHERE email = ?", [email], (err, results) => {
    if (err) return res.status(500).json({ message: "Database error" });

    const user = results[0];
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
    const expiresAt = new Date(Date.now() + 60 * 60 * 1000); // 1 hour

    db.query("DELETE FROM password_resets WHERE user_id = ?", [user.id], (err) => {
      if (err) return res.status(500).json({ message: "Database error" });

      db.query(
        "INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, ?)",
        [user.id, tokenHash, expiresAt],
        async (err) => {
          if (err) return res.status(500).json({ message: "Database error" });

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
        }
      );
    });
  });
});

// Reset password
app.post("/api/reset-password", authLimiter, (req, res) => {
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
  db.query(
    "SELECT user_id FROM password_resets WHERE token_hash = ? AND expires_at > NOW() LIMIT 1",
    [tokenHash],
    async (err, results) => {
      if (err) return res.status(500).json({ message: "Database error" });
      if (!results.length) {
        return res.status(400).json({ message: "Invalid or expired reset token" });
      }

      const userId = results[0].user_id;
      const hashedPassword = await bcrypt.hash(password, 10);

      db.query("UPDATE users SET password = ? WHERE id = ?", [hashedPassword, userId], (err) => {
        if (err) return res.status(500).json({ message: "Database error" });

        db.query("DELETE FROM password_resets WHERE user_id = ?", [userId], (err) => {
          if (err) return res.status(500).json({ message: "Database error" });
          return res.json({ message: "Password reset successful. You can now sign in." });
        });
      });
    }
  );
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

// Admin: list users (excluding passwords)
app.get("/api/admin/users", authRequired, adminOnly, (req, res) => {
  const search = (req.query.search || "").trim();
  let sql =
    "SELECT id, username, email, role, service, phone, location, about FROM users";
  const params = [];

  if (search) {
    sql += " WHERE username LIKE ? OR email LIKE ? OR role LIKE ? OR service LIKE ? OR location LIKE ?";
    const term = `%${search}%`;
    params.push(term, term, term, term, term);
  }

  sql += " ORDER BY id DESC";

  db.query(sql, params, (err, results) => {
    if (err) return res.status(500).json({ message: "Database error" });
    return res.json(results);
  });
});

// Get providers (optional search via ?search=)
app.get("/api/users", (req, res) => {
  const search = (req.query.search || "").trim();
  let sql =
    "SELECT id, username, role, service, phone, location, about FROM users WHERE role = 'provider' AND service IS NOT NULL AND service <> ''";
  const params = [];

  if (search) {
    sql += " AND (username LIKE ? OR service LIKE ? OR location LIKE ?)";
    const term = `%${search}%`;
    params.push(term, term, term);
  }

  db.query(sql, params, (err, results) => {
    if (err) return res.status(500).json({ message: "Database error" });
    return res.json(results);
  });
});

// Start server
const PORT = process.env.PORT || 5000;
app.listen(PORT, () => {
  console.log(`Server running on http://localhost:${PORT}`);
});
