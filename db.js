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

const supabaseUrl = process.env.SUPABASE_URL;
const supabaseServiceRoleKey = process.env.SUPABASE_SERVICE_ROLE_KEY;

if (!supabaseUrl || !supabaseServiceRoleKey) {
  throw new Error("SUPABASE_URL and SUPABASE_SERVICE_ROLE_KEY must be set");
}

const db = createClient(supabaseUrl, supabaseServiceRoleKey, {
  auth: { autoRefreshToken: false, persistSession: false }
});

module.exports = db;
