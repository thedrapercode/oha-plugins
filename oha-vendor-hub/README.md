# OHA Vendor Hub v1.6.0

Complete vendor management system for Ohio Haunters Convention 2026.

## 🎯 What's Included

- ✅ Initial vendor application form (with duplicate prevention)
- ✅ Final application form with e-signature
- ✅ Stripe payment integration ($250/booth)
- ✅ Board Hub admin dashboard
- ✅ Email automation for all statuses
- ✅ Pre-populated vendor links
- ✅ Mobile-responsive design

## 🚀 Quick Install

1. **Upload to WordPress:**
   - Go to Plugins → Add New → Upload Plugin
   - Choose `oha-vendor-hub-v1.6.zip`
   - Click "Install Now"
   - Click "Activate"

2. **Configure Stripe:**
   - Go to Vendor Applications → Settings
   - Scroll to "Stripe Payment Settings"
   - Add your Test and Live payment link URLs
   - Save settings

3. **Create Pages:**
   - Create page: "Vendor Application" with shortcode `[oha_vendor_form]`
   - Create page: "Final Application" with shortcode `[oha_vendor_final_form]`
   - Create page: "Board Hub" with shortcode `[oha_vendor_board_hub]` (admin only)

4. **Test:**
   - Submit test application
   - Use test card: 4242 4242 4242 4242
   - Verify everything works

## 📋 Required Shortcodes

**For Public:**
- `[oha_vendor_form]` - Initial application form
- `[oha_vendor_final_form]` - Final application with payment

**For Admin:**
- `[oha_vendor_board_hub]` - Vendor management dashboard

## 💳 Stripe Setup

1. Create Stripe account at https://stripe.com (free)
2. Go to Payment Links in Stripe Dashboard
3. Create payment link:
   - Product: "OHA Convention 2026 Vendor Booth"
   - Price: $250.00
   - Enable "Adjustable quantity"
   - Enable "Collect customer email"
4. Copy link URL and paste in WordPress settings
5. Create separate links for test and live modes

## 🔐 E-Signature System

**Features:**
- HTML5 Canvas signature pad
- Works with mouse or touch
- Legally binding (ESIGN Act compliant)
- Captures: signature, name, date, IP address
- 100% FREE (no third-party services)

**What vendors sign:**
- All Mansfield Reformatory venue rules
- Load-in/load-out schedule
- Setup restrictions
- Refund policy
- Booth requirements

## 📧 Email Automation

Configure automated emails for:
- Submitted - Initial application received
- In Review - Being reviewed
- Approved - Initial app approved
- Agreement Signed - Signed and ready to pay
- Paid - Payment received
- Denied - Not approved

Edit templates in: Vendor Applications → Settings → Email Automations

## 🎨 Customization

**Booth Price:**
Edit in final-application.php around line 220

**Venue Terms:**
Edit in final-application.php around line 170

**Form Fields:**
Edit in Settings → Fields (JSON format)

**Email Templates:**
Edit in Settings → Email Automations

## 📱 Mobile Support

- Fully responsive design
- Touch-enabled signature pad
- Works on iOS and Android
- Tablet and phone optimized

## 🆘 Troubleshooting

**"Stripe not configured" warning:**
- Add payment links in Settings
- Make sure mode matches (test/live)

**Signature pad not working:**
- Clear browser cache
- Try different browser
- Check JavaScript console for errors

**Duplicate submissions:**
- Already fixed in this version
- Clear all caches after install

**Form not submitting:**
- Check all required fields filled
- Verify signature drawn
- Check error messages

## 📊 Vendor Workflow

```
Vendor submits initial application
         ↓
Board reviews in Board Hub
         ↓
Board sends final application link
         ↓
Vendor signs agreement
         ↓
Vendor pays via Stripe
         ↓
Board confirms payment
         ↓
Vendor spot confirmed!
```

## 🔒 Security

- Nonce verification on all forms
- Input sanitization
- CSRF protection
- SQL injection prevention
- Stripe handles payment security
- PCI compliance through Stripe

## 📞 Support

**Documentation:**
- readme.txt - WordPress plugin info
- This file - Quick start guide

**Stripe:**
- https://stripe.com/docs
- https://dashboard.stripe.com

**WordPress:**
- https://wordpress.org/support

## ✅ Post-Install Checklist

- [ ] Plugin activated
- [ ] Stripe test link configured
- [ ] Stripe live link configured
- [ ] Initial application page created
- [ ] Final application page created
- [ ] Board Hub page created (admin only)
- [ ] Test application submitted
- [ ] Test signature drawn
- [ ] Test payment completed
- [ ] Email automation configured
- [ ] All caches cleared

## 🎓 Training Board Members

Board members need to know:
1. How to access Board Hub
2. How to review applications
3. How to send final app links
4. How to verify payments in Stripe
5. How to update application status

**Access:** WordPress login → Vendor Applications → Board Hub page

## 💰 Costs

**Plugin:** FREE

**Stripe Fees:**
- Test mode: FREE
- Live mode: 2.9% + $0.30 per transaction
- Example: $250 booth = $7.55 fee, you receive $242.45

**E-Signature:** FREE (no third-party service needed)

## 📈 Going Live

1. Test everything in test mode
2. Complete Stripe bank account setup
3. Switch to "Live Mode" in settings
4. Train board members
5. Monitor first few submissions
6. Celebrate! 🎉

## 🔄 Updates

To update the plugin:
1. Backup current plugin
2. Deactivate plugin
3. Delete old plugin folder
4. Upload new version
5. Activate plugin
6. Clear all caches

## 🤝 Credits

Developed for Ohio Haunters Association  
Ohio Haunters Convention 2026  
Mansfield Reformatory, Mansfield Ohio  
May 9-10, 2026

---

**Version:** 1.6.0  
**Release Date:** January 2026  
**Requires:** WordPress 5.0+, PHP 7.4+  
**License:** GPL v2 or later

For questions or support, contact the OHA development team.
