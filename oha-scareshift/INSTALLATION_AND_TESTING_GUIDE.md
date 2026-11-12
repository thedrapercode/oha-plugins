# ScareShift v1.4.2 - Tension System Release
## Complete Setup & Testing Guide

**Release Date:** January 11, 2025  
**Version:** 1.4.2-20250111  
**Focus:** Dynamic tension through timers, dread, fatigue, and dramatic reveals

---

## 🎯 WHAT'S NEW IN v1.4.2

### Major Features Added:
1. **⏱ Decision Timer** - 15-second countdown creates pressure
2. **😱 Dynamic Dread** - Builds during hesitation, affects gameplay
3. **💪 Fatigue Accumulation** - Physical/mental toll over encounters
4. **📊 Beat-by-Beat Reveals** - Dramatic outcome sequences
5. **⚠️ Visual Warnings** - Screen effects for critical states
6. **🎭 Crew Stats Display** - Shows team capabilities during shift

---

## 📦 INSTALLATION INSTRUCTIONS

### Prerequisites:
- WordPress 5.0 or higher
- PHP 7.4 or higher
- Active WordPress user account
- Existing ScareShift installation (v1.4.1 or earlier)

### Step 1: Backup Current Installation
```bash
# Via FTP or cPanel, download a backup of:
/wp-content/plugins/scareshift-1.4.1/
```

**Important:** Also backup your database (user_meta table contains character data)

### Step 2: Deactivate Current Plugin
1. Log in to WordPress Admin
2. Navigate to **Plugins** → **Installed Plugins**
3. Find "ScareShift - Haunter RPG System"
4. Click **Deactivate**

**Note:** Do NOT delete the old plugin yet - we're replacing it

### Step 3: Upload v1.4.2
**Method A - FTP/SFTP:**
```bash
# 1. Delete old plugin folder
rm -rf /wp-content/plugins/scareshift-1.4.1/

# 2. Upload new version
# Extract scareshift-1.4.2.zip to:
/wp-content/plugins/scareshift-1.4.2/
```

**Method B - WordPress Admin (Recommended):**
1. Navigate to **Plugins** → **Add New**
2. Click **Upload Plugin**
3. Choose `scareshift-1.4.2.zip`
4. Click **Install Now**
5. Do NOT activate yet

### Step 4: Verify File Structure
Ensure the following files exist:
```
/wp-content/plugins/scareshift-1.4.2/
├── scareshift.php (version 1.4.2-20250111)
├── assets/
│   ├── js/
│   │   ├── shift-mode.js (updated)
│   │   └── tension-system.js (NEW)
│   └── css/
│       ├── scareshift.css
│       ├── scareshift-visual-v2.css
│       └── scareshift-tension.css (NEW)
├── includes/
│   └── class-shift-mode.php (updated)
└── config/
    └── scenario-library.php
```

### Step 5: Activate Plugin
1. Navigate to **Plugins** → **Installed Plugins**
2. Find "ScareShift - Haunter RPG System" (v1.4.2)
3. Click **Activate**

### Step 6: Clear Cache
**Important:** Clear all caches to ensure new assets load:

```bash
# If using WP-CLI:
wp cache flush

# If using a cache plugin:
# - WP Super Cache: Settings → WP Super Cache → Delete Cache
# - W3 Total Cache: Performance → Dashboard → Empty All Caches
# - WP Rocket: Settings → Clear Cache
```

**Browser Cache:**
- Hard refresh: `Ctrl+Shift+R` (Windows/Linux) or `Cmd+Shift+R` (Mac)
- Or open DevTools (F12) → Network tab → Check "Disable cache"

---

## ✅ TESTING CHECKLIST

### Phase 1: Basic Functionality (5 minutes)
- [ ] Plugin activates without errors
- [ ] Navigate to Shift Mode page (create page with `[scareshift_shift_mode]` shortcode if needed)
- [ ] Crew selection screen loads
- [ ] Can select 1-3 characters
- [ ] "Start Shift" button enables when crew selected
- [ ] Shift starts without errors

### Phase 2: Visual Systems (5 minutes)
- [ ] Scene state panel displays (Dread, Momentum, Fatigue bars)
- [ ] Crew stats display shows selected characters
- [ ] Wave progress bar shows correctly
- [ ] Action buttons render with hover effects
- [ ] All CSS loads properly (no unstyled elements)

### Phase 3: Timer System (10 minutes)
**Test 1: Normal Timer Flow**
1. Start a shift with any crew
2. [ ] Timer appears at top of encounter
3. [ ] Timer shows "⏱ 15s" initially
4. [ ] Timer bar decreases smoothly (green → yellow → red)
5. [ ] Make a choice within 15 seconds
6. [ ] Timer stops when choice made
7. [ ] Timer disappears after choice

**Test 2: Hesitation Penalties**
1. Start new encounter
2. [ ] Wait 5 seconds without choosing
3. [ ] Dread bar increases (hesitation penalty)
4. [ ] Wait another 5 seconds (10 total)
5. [ ] Dread increases again
6. [ ] Choose action before timeout

**Test 3: Timeout Handling**
1. Start new encounter
2. [ ] Let timer reach 0 without choosing
3. [ ] First action auto-selects (timeout penalty)
4. [ ] Dread increases by +2
5. [ ] Fatigue increases by +1
6. [ ] Outcome displays normally

### Phase 4: Dread System (10 minutes)
**Test 1: Dread Building**
1. [ ] Start shift with fresh character
2. [ ] Hesitate during encounters (let timer go below 10s)
3. [ ] Dread bar increases visibly
4. [ ] Dread value updates (e.g., "2/5")
5. [ ] Bar animates with pulse effect

**Test 2: High Dread Warning**
1. [ ] Build dread to 4 or 5
2. [ ] Warning message appears: "⚠️ Guests are on edge!"
3. [ ] Screen shake effect triggers
4. [ ] Warning fades after 3 seconds
5. [ ] Warning color is red

### Phase 5: Fatigue System (10 minutes)
**Test 1: Encounter-Based Fatigue**
1. [ ] Complete 3 encounters
2. [ ] Fatigue increases by +1
3. [ ] Complete 6 encounters total
4. [ ] Fatigue is now 2/5

**Test 2: Choice-Based Fatigue**
1. [ ] Choose actions with keywords like "jump", "chase", "scream"
2. [ ] Fatigue increases by +1
3. [ ] Fatigue bar updates and pulses

**Test 3: High Fatigue Effects**
1. [ ] Build fatigue to 4 or 5
2. [ ] Warning appears: "💪 You're exhausted!"
3. [ ] Screen dims slightly
4. [ ] Animations become slower
5. [ ] Warning color is orange

### Phase 6: Beat-by-Beat Reveals (15 minutes)
**Critical Test - This is the main "feel" improvement**

1. Make a choice in encounter
2. [ ] Step 1: "Resolving..." spinner appears (800ms)
3. [ ] Step 2: Outcome message displays (1200ms)
4. [ ] Step 3: State changes animate (Dread/Momentum/Fatigue)
5. [ ] State bars pulse and update
6. [ ] Step 4: "SUCCESS" or "SETBACK" displays dramatically
7. [ ] Step 5: Stats show (guests scared, incidents)
8. [ ] Step 6: Character growth shows (if any stat changes)
9. [ ] Continue button appears
10. [ ] Each beat feels separated, not instant

**Timing Check:**
- [ ] Total reveal takes 4-5 seconds (not instant)
- [ ] Each beat feels distinct
- [ ] Doesn't feel too slow/boring
- [ ] Creates anticipation

### Phase 7: Complete Shift Flow (20 minutes)
**Full Playthrough Test:**
1. [ ] Start shift with 2-3 characters
2. [ ] Complete Wave 1 (5-10 encounters)
3. [ ] Experience mix of timeouts and normal choices
4. [ ] See dread/fatigue build over wave
5. [ ] Complete wave without errors
6. [ ] "Next Wave" button works
7. [ ] Complete Wave 2
8. [ ] State resets appropriately
9. [ ] Complete Wave 3
10. [ ] "View Shift Results" shows
11. [ ] Results screen displays correctly
12. [ ] "Start New Shift" returns to crew selection
13. [ ] Can start another shift

### Phase 8: Edge Cases (10 minutes)
- [ ] Refresh during encounter (state persists)
- [ ] Timer still works after refresh
- [ ] Multiple rapid clicks don't break system
- [ ] Timeout during last encounter of wave
- [ ] Character with all low stats
- [ ] Character with all high stats
- [ ] Solo character (crew size 1)
- [ ] Full crew (crew size 3)

### Phase 9: Mobile Responsiveness (5 minutes)
Test on mobile device or use DevTools mobile view:
- [ ] Timer displays properly on small screen
- [ ] Crew stats wrap correctly
- [ ] Action buttons are tappable
- [ ] Beat reveals don't overflow
- [ ] State bars visible
- [ ] Warnings display correctly

### Phase 10: Browser Compatibility (10 minutes)
Test in multiple browsers:
- [ ] Chrome/Edge (Chromium)
- [ ] Firefox
- [ ] Safari (if available)
- [ ] Mobile Chrome
- [ ] Mobile Safari

---

## 🔧 TROUBLESHOOTING GUIDE

### Issue: Timer Doesn't Appear
**Symptoms:** No timer shows at top of encounter

**Solutions:**
1. Hard refresh browser (Ctrl+Shift+R)
2. Check browser console for JavaScript errors (F12)
3. Verify `tension-system.js` loaded:
   - F12 → Network tab → Search for "tension-system.js"
   - Should show status 200
4. Check if element exists in DOM:
   - F12 → Console → Type: `document.getElementById('scareshift-timer')`
   - Should return element (not null)

**Fix:**
```bash
# Clear WP cache
wp cache flush

# Verify file exists
ls wp-content/plugins/scareshift-1.4.2/assets/js/tension-system.js
```

### Issue: Timer Doesn't Count Down
**Symptoms:** Timer appears but stays at 15s

**Solutions:**
1. Check console for errors
2. Verify `ScareShiftTension` global exists:
   - Console: `window.ScareShiftTension`
   - Should show object with methods
3. Check if `startTimer()` was called:
   - Console: `window.ScareShiftTension.timerInterval`
   - Should show interval ID (number)

### Issue: Dread/Fatigue Don't Update
**Symptoms:** Bars don't animate or values don't change

**Solutions:**
1. Verify bar elements exist:
   ```javascript
   // In console:
   document.getElementById('dread-bar')
   document.getElementById('fatigue-bar')
   ```
2. Check if CSS loaded:
   - Look for pulse animation on bars
   - Verify `scareshift-tension.css` in Network tab
3. Check game state:
   ```javascript
   // In console:
   window.ScareShift.ShiftMode.wave.dread
   window.ScareShift.ShiftMode.wave.fatigue
   ```

### Issue: Beat Reveals Don't Work
**Symptoms:** Outcomes still appear instantly

**Solutions:**
1. Verify tension system initialized:
   ```javascript
   // In console:
   window.ScareShift.ShiftMode.tensionSystem
   ```
2. Check if `showOutcomeInBeats` method exists:
   ```javascript
   window.ScareShiftTension.showOutcomeInBeats
   ```
3. Look for errors during outcome display
4. Verify all outcome data returns from server

### Issue: Warnings Don't Appear
**Symptoms:** High dread/fatigue but no warning messages

**Solutions:**
1. Check threshold values:
   ```javascript
   window.ScareShiftTension.DREAD_WARNING_THRESHOLD  // Should be 4
   window.ScareShiftTension.FATIGUE_WARNING_THRESHOLD  // Should be 4
   ```
2. Verify current values reach thresholds
3. Check if warning elements created in DOM
4. Verify CSS animations loaded

### Issue: Crew Stats Don't Display
**Symptoms:** No crew stats panel during shift

**Solutions:**
1. Check if crew stats returned from server:
   ```javascript
   window.ScareShift.ShiftMode.wave.crew_stats
   ```
2. Verify backend is calculating crew stats
3. Check if HTML conditional is true:
   - crew_size should be > 0
4. Look for CSS hiding the element

### Issue: Performance Problems
**Symptoms:** Slow animations, lag, choppy timer

**Solutions:**
1. Check browser performance:
   - F12 → Performance tab → Record session
   - Look for long tasks or layout thrashing
2. Disable other plugins temporarily
3. Check server response times:
   - Network tab → Look at API calls
   - Should be < 500ms
4. Reduce encounter frequency if needed

### Issue: State Not Persisting
**Symptoms:** Refresh loses progress

**Solutions:**
1. Check if user is logged in
2. Verify user_meta saving:
   ```php
   // In WP admin or via WP-CLI:
   wp user meta get [user_id] scareshift_active_shift
   ```
3. Check REST API nonce validity
4. Look for PHP errors in server logs

---

## 🔒 SECURITY NOTES

### Data Security:
✅ **Secured:**
- User authentication required (WordPress user login)
- REST API nonce validation on all requests
- Server-side state authority (client can't fake outcomes)
- XSS protection via escapeHtml() on all user input
- SQL injection prevented (using WP database abstraction)

### Session Security:
- Shift state stored in user_meta (WordPress native)
- Nonce refreshes automatically
- No sensitive data in localStorage/sessionStorage
- Timer is client-side (cosmetic) but penalties server-validated

### Input Validation:
- Choice validation against available options
- Encounter ID verification
- Character ownership checked
- Crew selection limits enforced (1-3 characters)

### Recommendations:
1. **Use HTTPS** - Required for secure session handling
2. **Keep WordPress Updated** - Security patches
3. **Strong Passwords** - For all user accounts
4. **Regular Backups** - Database includes character data
5. **Limit Login Attempts** - Use plugin like "Limit Login Attempts Reloaded"

---

## 🚀 API MIGRATION READINESS

### Current Architecture:
```
WordPress Login → PHP Session → user_meta Storage
     ↓
REST API Endpoints (WordPress)
     ↓
Frontend JS (jQuery + Vanilla JS)
```

### v1.4.2 Improvements for Future API:
✅ **State Management:**
- Game state isolated in ShiftMode object
- Easy to serialize for external API
- Shift state already JSON-compatible

✅ **Separation of Concerns:**
- tension-system.js is standalone module
- No WordPress dependencies in tension logic
- shift-mode.js can be easily adapted for API calls

✅ **Data Structure:**
- Clean shift/wave/encounter model
- Standardized response format
- Portable to any backend

### Migration Path:
When ready to move to external API:

1. **Keep WordPress for:**
   - User authentication (JWT tokens)
   - Character storage (until migrated)
   - Community pages

2. **Move to API:**
   - Shift state management
   - Scenario selection
   - Outcome calculation
   - Progression tracking

3. **Frontend Changes:**
   ```javascript
   // Replace:
   fetch(SCARESHIFT_SHIFT.restUrl + 'shift/choose', ...)
   
   // With:
   fetch('https://api.scareshift.com/v1/shift/choose', {
       headers: {
           'Authorization': 'Bearer ' + jwt_token
       },
       ...
   })
   ```

4. **Data Migration:**
   - Export user_meta to API database
   - Map WordPress user IDs to API user IDs
   - Maintain backward compatibility during transition

---

## 📊 SUCCESS METRICS

### How to Know v1.4.2 is Working:

**Qualitative (Feel):**
- ❓ Does the timer create **pressure**?
- ❓ Does hesitation feel **dangerous**?
- ❓ Do outcomes feel **satisfying** (not flat)?
- ❓ Is it more **engaging** than v1.4.1?
- ❓ Does fatigue make you feel **exhausted**?
- ❓ Does high dread make you feel **anxious**?

**Quantitative (Metrics):**
- ⏱ Average decision time: **8-12 seconds** (good pressure)
- 😱 Dread reaches 4-5 in: **30-50% of waves**
- ⏰ Timeout rate: **< 10% of encounters**
- 💪 Fatigue reaches 4-5 in: **50-70% of waves**
- 📊 Average beat reveal time: **4-5 seconds** (not instant, not slow)

---

## 🐛 KNOWN ISSUES & LIMITATIONS

### Current Limitations:
1. **Timer Duration:** Fixed at 15 seconds (not adjustable in UI)
2. **No Audio:** No sound effects for timer/warnings
3. **No Pause:** Can't pause during encounter
4. **Desktop Optimized:** Mobile works but best on desktop
5. **No Difficulty Scaling:** Timer same for all difficulty levels

### Planned Improvements (v1.5.0+):
- [ ] Adjustable timer difficulty settings
- [ ] Sound effects and music
- [ ] Pause functionality
- [ ] Mobile-optimized controls
- [ ] Timer duration based on encounter difficulty
- [ ] More granular fatigue effects
- [ ] Dread consequences (guests leave early, etc.)

---

## 📞 SUPPORT & FEEDBACK

### Getting Help:
1. Check troubleshooting guide above
2. Review browser console for errors (F12)
3. Check WordPress error logs
4. Test with default WordPress theme
5. Disable other plugins to isolate issue

### Reporting Issues:
Include in your report:
- WordPress version
- PHP version
- Browser & version
- ScareShift version (1.4.2-20250111)
- Console errors (screenshot or copy text)
- Steps to reproduce
- Expected vs actual behavior

### Providing Feedback:
**Priority Questions:**
1. Does the timer create tension?
2. Are beat reveals satisfying?
3. Do dread/fatigue feel meaningful?
4. Is the game more engaging than before?
5. What feels too fast/slow/unclear?

---

## 📝 VERSION HISTORY

### v1.4.2 (January 11, 2025)
- Added decision timer system
- Added dynamic dread building
- Added fatigue accumulation
- Added beat-by-beat outcome reveals
- Added visual warnings for critical states
- Added crew stats display during shift
- Improved state bar animations
- Enhanced visual feedback

### v1.4.1 (December 14, 2024)
- Added crew stats system
- Fixed decimal display issues
- Prevented repeat scenarios
- Backend crew bonuses implemented

### v1.4.0 (December 13, 2024)
- Major visual overhaul
- Added scene state panel
- Redesigned action buttons
- Professional gaming aesthetics

---

## 🎉 TESTING COMPLETE!

Once you've completed the testing checklist above, you're ready to:
1. ✅ Use the plugin with confidence
2. 🎮 Start gathering user feedback
3. 📊 Track engagement metrics
4. 🚀 Plan next features based on data

**Congratulations on deploying v1.4.2!** 🎭

The tension is real. The game is alive. Let's see how players respond!
