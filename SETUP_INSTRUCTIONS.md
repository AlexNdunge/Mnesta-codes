# JuaKazi Setup Instructions

## Step-by-Step Setup Guide

### 1. Update Database Table Structure
Run this script to add missing columns to the users table:
```
http://localhost/Juakazi/update_users_table.php
```

This will add:
- `phone` (VARCHAR 20)
- `location` (VARCHAR 255)
- `about` (TEXT)
- `rating` (DECIMAL 3,2)
- `review_count` (INT)
- `profile_image` (VARCHAR 255)

### 2. Add Sample Provider Data (Optional)
Run this script to populate the database with sample providers:
```
http://localhost/Juakazi/add_sample_providers.php
```

This will add 6 sample service providers with different services.

### 3. Test the Setup
Run the debug script to verify everything is working:
```
http://localhost/Juakazi/debug_services.php
```

### 4. View Services Page
Once setup is complete, view the services page:
```
http://localhost/Juakazi/services.html
```

## What's Been Updated

### signup.html
Now includes fields for providers:
- ✅ Username
- ✅ Email
- ✅ Account Type (Customer/Provider)
- ✅ Service Type (for providers)
- ✅ Phone Number (required for providers)
- ✅ Location (required for providers)
- ✅ About Your Service (required for providers)
- ✅ Profile Image Upload (optional)
- ✅ Password
- ✅ Confirm Password

### register.php
Updated to handle:
- ✅ Phone number validation
- ✅ Location validation
- ✅ About section validation
- ✅ Profile image upload (with file validation)
- ✅ All fields saved to database

### services.html
Now displays:
- ✅ Provider username
- ✅ Service offered
- ✅ Location
- ✅ About section
- ✅ Rating and review count
- ✅ Phone number (clickable)
- ✅ Profile image (with fallback to initials)

### services.php
Updated to:
- ✅ Fetch data directly from users table
- ✅ Return JSON for AJAX requests
- ✅ Support search and filtering
- ✅ Dynamic category loading

## Database Structure

### Users Table Columns:
```sql
- id (INT, AUTO_INCREMENT, PRIMARY KEY)
- username (VARCHAR)
- email (VARCHAR)
- role (VARCHAR) - 'customer' or 'provider'
- service (VARCHAR) - Service type for providers
- password (VARCHAR) - Hashed password
- created_at (TIMESTAMP)
- phone (VARCHAR) - Phone number
- location (VARCHAR) - Location/address
- about (TEXT) - Description of services
- rating (DECIMAL) - Average rating (0.0 - 5.0)
- review_count (INT) - Number of reviews
- profile_image (VARCHAR) - Path to profile image
```

## Testing

### Register a New Provider:
1. Go to: `http://localhost/Juakazi/signup.html`
2. Select "Provider - Offer services"
3. Fill in all required fields
4. Upload a profile image (optional)
5. Submit the form

### View Providers:
1. Go to: `http://localhost/Juakazi/services.html`
2. Browse all providers
3. Use search to find specific providers
4. Filter by service category

## Troubleshooting

### "Failed to load services" Error:
1. Check if XAMPP Apache and MySQL are running
2. Run `update_users_table.php` to ensure all columns exist
3. Run `debug_services.php` to see detailed error information
4. Check browser console for JavaScript errors

### Database Connection Issues:
- Verify database name is `juakazi_db`
- Check MySQL credentials (default: root with no password)
- Ensure database exists

### File Upload Issues:
- Check that `uploads/profiles/` directory exists
- Verify directory has write permissions
- Maximum file size: 2MB
- Allowed formats: JPG, JPEG, PNG, GIF

## Next Steps

After setup, you can:
1. Register as a provider with full profile information
2. View all providers on the services page
3. Search and filter providers by service type
4. See detailed provider information including ratings and contact details
