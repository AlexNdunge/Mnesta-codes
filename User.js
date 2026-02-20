require("dotenv").config();
const express = require("express");
const mysql = require("mysql2/promise");
const cors = require("cors");
const bcrypt = require("bcrypt");

const app = express();
app.use(cors());
app.use(express.json());

// MySQL connection pool
const db = mysql.createPool({
  host: process.env.DB_HOST || "localhost",
  user: process.env.DB_USER || "root",
  password: process.env.DB_PASSWORD || "@Messi#10",
  database: process.env.DB_NAME || "juakazi",
});

// ---------------- Signup Endpoint ----------------
app.post("/api/signup", async (req, res) => {
  const { username, email, password, role, service, phone, location, about } = req.body;

  if (!username || !email || !password || !role) {
    return res.status(400).json({ message: "Missing required fields" });
  }

  try {
    // Hash password
    const hashedPassword = await bcrypt.hash(password, 10);

    const [rows] = await db.query(
      `INSERT INTO users 
      (username, email, password, role, service, phone, location, about)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?)`,
      [username, email, hashedPassword, role, service || null, phone || null, location || null, about || null]
    );

    res.json({ message: "User created successfully", userId: rows.insertId });
  } catch (err) {
    console.error(err);
    res.status(500).json({ message: "Database error", error: err.message });
  }
});

// ---------------- Get Users Endpoint ----------------
app.get("/api/users", async (req, res) => {
  const { search, role, service } = req.query;

  try {
    let query = "SELECT id, username, role, service, phone, location, about FROM users WHERE 1=1";
    const params = [];

    if (role) {
      query += " AND role = ?";
      params.push(role);
    }

    if (service) {
      query += " AND service LIKE ?";
      params.push(`%${service}%`);
    }

    if (search) {
      query += " AND (username LIKE ? OR service LIKE ? OR location LIKE ?)";
      const searchTerm = `%${search}%`;
      params.push(searchTerm, searchTerm, searchTerm);
    }

    const [users] = await db.query(query, params);

    res.json(users.map(u => ({
      id: u.id,
      name: u.username,
      role: u.role,
      service: u.service,
      phone: u.phone,
      location: u.location,
      about: u.about
    })));
  } catch (err) {
    console.error(err);
    res.status(500).json({ message: "Database error", error: err.message });
  }
});

// ---------------- Start Server ----------------
const PORT = process.env.PORT || 5000;
app.listen(PORT, () => console.log(`Server running on http://localhost:${PORT}`));