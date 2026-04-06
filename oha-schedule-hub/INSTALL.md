# Quick Installation Guide

## Step 1: Upload Plugin

1. Download the `oha-schedule-hub.zip` file
2. In WordPress admin, go to **Plugins > Add New**
3. Click **Upload Plugin**
4. Choose the ZIP file and click **Install Now**
5. Click **Activate Plugin**

## Step 2: Import Your Schedule

**Option A: Quick Import (Recommended)**
1. Go to **Schedule > Bulk Import**
2. Click **"Import 2026 Schedule"** button
3. Your pre-loaded schedule will be imported automatically

**Option B: Import from Spreadsheet**
1. Go to **Schedule > Bulk Import**
2. Upload your Excel file or paste CSV data
3. Click **Import from File** or **Import from CSV**

## Step 3: Configure Settings

1. Go to **Schedule > Settings**
2. Set your preferred time format (12h or 24h)
3. Configure display options
4. Save settings

## Step 4: Display Schedule

Add this shortcode to any page or post:
```
[oha_schedule]
```

That's it! Your schedule is live.

## Additional Options

### Create a Schedule Page
1. Create new page: **Pages > Add New**
2. Title it "Convention Schedule"
3. Add shortcode: `[oha_schedule view="timeline"]`
4. Publish

### Create Admin Management Page (Optional)
1. Create new page: **Pages > Add New**
2. Title it "Manage Schedule"
3. Add shortcode: `[oha_schedule_admin]`
4. Set page to **Private** (only visible to logged-in users)
5. Publish

### Add to Your PWA
The schedule automatically works with your OHA Convention PWA:
- Data is cached for offline viewing
- Updates automatically when online
- Shows offline indicator when needed

## Quick Shortcode Reference

**Basic Display:**
```
[oha_schedule]
```

**Timeline View:**
```
[oha_schedule view="timeline"]
```

**Grid View:**
```
[oha_schedule view="grid"]
```

**List View:**
```
[oha_schedule view="list"]
```

**Saturday Only:**
```
[oha_schedule day="Saturday"]
```

**Without Filters:**
```
[oha_schedule show_filters="0"]
```

**Frontend Admin:**
```
[oha_schedule_admin]
```

## Need Help?

Check the full README.md for detailed documentation, or contact justin@ohiohaunt.com
