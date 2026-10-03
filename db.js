const { createClient } = require("@supabase/supabase-js");

// Single source of truth for the Supabase client. Previously this exact
// client-creation logic was duplicated here AND inline in server.js, with
// two different behaviors on missing config (this file threw; server.js
// degraded gracefully) — server.js's version is kept, since throwing here
// would crash the whole app at boot (including the frontend static files
// and any non-DB routes) just because Supabase isn't configured yet, which
// is a real workflow for local frontend-only work.
//
// Callers should treat this as possibly null and check before using it —
// see ensureSupabaseConfigured() in server.js.
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

module.exports = supabase;
