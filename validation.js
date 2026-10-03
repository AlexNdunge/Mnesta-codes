const { z } = require("zod");

// ---------------------------------------------------------------------------
// Shared field rules
// ---------------------------------------------------------------------------

const email = z.string().trim().toLowerCase().email().max(254);

// 8-128 chars, must contain at least one letter and one number.
// (Mirrors the strength rule the app already enforces at signup/reset.)
const strongPassword = z
  .string()
  .min(8, "Password must be at least 8 characters")
  .max(128, "Password is too long")
  .refine((v) => /[A-Za-z]/.test(v), "Password must include a letter")
  .refine((v) => /[0-9]/.test(v), "Password must include a number");

// Signin only checks presence + a sane upper bound — composition rules don't
// apply here, or existing accounts with older passwords couldn't log in.
const loginPassword = z.string().min(1, "Password is required").max(128);

const kenyanPhone = z
  .string()
  .trim()
  .regex(/^07[0-9]{8,9}$/, "Invalid phone number. Use 07XXXXXXXXX format.");

const username = z
  .string()
  .trim()
  .min(2, "Username must be at least 2 characters")
  .max(50, "Username must be 50 characters or fewer")
  .regex(/^[\p{L}0-9 .'-]+$/u, "Username has unsupported characters");

const roleInput = z.preprocess((v) => {
  const value = String(v || "").trim().toLowerCase();
  return value === "customer" ? "client" : value;
}, z.enum(["client", "provider", "admin"], {
  errorMap: () => ({ message: "Role must be client, provider, or admin" })
}));

const shortText = (max) => z.string().trim().max(max).optional().or(z.literal("").transform(() => undefined));

const positiveIntId = z.coerce
  .number({ invalid_type_error: "Invalid id" })
  .int("Invalid id")
  .positive("Invalid id");

// Pagination: capped so nobody can force a full-table dump in one request.
// Default matches the cap (not something lower) because services.html and
// admin-dashboard.html don't send limit/offset today — this keeps their
// current "show everything" behavior intact until a table actually grows
// past 100 rows, at which point the cap (not an unbounded query) kicks in.
const paginationQuery = z.object({
  search: z.string().trim().max(100).optional().default(""),
  limit: z.coerce.number().int().min(1).max(100).optional().default(100),
  offset: z.coerce.number().int().min(0).optional().default(0)
});

// ---------------------------------------------------------------------------
// Route schemas
// ---------------------------------------------------------------------------

const signupSchema = z
  .object({
    username,
    email,
    password: strongPassword,
    role: roleInput,
    service: shortText(100),
    phone: z.string().trim().optional(),
    location: shortText(120),
    about: shortText(1000)
  })
  .superRefine((data, ctx) => {
    if (data.role === "provider") {
      if (!data.service) {
        ctx.addIssue({ code: z.ZodIssueCode.custom, path: ["service"], message: "Service is required for providers!" });
      }
      if (!data.phone || !/^07[0-9]{8,9}$/.test(data.phone.trim())) {
        ctx.addIssue({ code: z.ZodIssueCode.custom, path: ["phone"], message: "A valid phone (07XXXXXXXXX) is required for providers" });
      }
    }
  });

const signinSchema = z.object({
  email,
  password: loginPassword
});

const forgotPasswordSchema = z.object({
  email
});

// Exactly 4 digits, nothing else — no leading "+", no whitespace, no letters.
const adminPinLoginSchema = z.object({
  pin: z.string().trim().regex(/^\d{4}$/, "PIN must be exactly 4 digits")
});

const resetPasswordSchema = z.object({
  token: z
    .string()
    .trim()
    .regex(/^[a-f0-9]{64}$/, "Invalid or expired reset token"),
  password: strongPassword
});

const createBookingSchema = z.object({
  provider_id: positiveIntId,
  customer_phone: kenyanPhone,
  notes: shortText(500)
});

const bookingIdParamSchema = z.object({
  id: positiveIntId
});

const updateBookingStatusSchema = z.object({
  status: z.enum(["confirmed", "in_progress", "completed", "cancelled"], {
    errorMap: () => ({ message: "Invalid status value" })
  })
});

const updatePaymentStatusSchema = z.object({
  payment_status: z.enum(["initiated", "pending", "success", "failed", "cancelled"], {
    errorMap: () => ({ message: "Invalid payment_status value" })
  })
});

// ---------------------------------------------------------------------------
// Express middleware factories
// ---------------------------------------------------------------------------

function formatZodError(error) {
  return error.issues.map((issue) => ({
    field: issue.path.join(".") || "(root)",
    message: issue.message
  }));
}

// Every existing page (signup.html, signin.html, services.html, etc.) reads
// only `data.message` for its error text. `errors` is included for anyone
// who wants per-field detail, but `message` carries the first real issue
// (e.g. "Invalid email address") rather than a generic "Validation failed"
// — otherwise every field error on every form would collapse into the same
// unhelpful string on screen.
function validationErrorResponse(zodError) {
  const errors = formatZodError(zodError);
  return { message: errors[0]?.message || "Validation failed", errors };
}

function validateBody(schema) {
  return (req, res, next) => {
    const result = schema.safeParse(req.body);
    if (!result.success) {
      return res.status(400).json(validationErrorResponse(result.error));
    }
    req.body = result.data;
    return next();
  };
}

function validateQuery(schema) {
  return (req, res, next) => {
    const result = schema.safeParse(req.query);
    if (!result.success) {
      return res.status(400).json(validationErrorResponse(result.error));
    }
    req.validatedQuery = result.data;
    return next();
  };
}

function validateParams(schema) {
  return (req, res, next) => {
    const result = schema.safeParse(req.params);
    if (!result.success) {
      return res.status(400).json(validationErrorResponse(result.error));
    }
    req.validatedParams = result.data;
    return next();
  };
}

module.exports = {
  schemas: {
    signupSchema,
    signinSchema,
    forgotPasswordSchema,
    adminPinLoginSchema,
    resetPasswordSchema,
    createBookingSchema,
    bookingIdParamSchema,
    updateBookingStatusSchema,
    updatePaymentStatusSchema,
    paginationQuery
  },
  validateBody,
  validateQuery,
  validateParams
};
