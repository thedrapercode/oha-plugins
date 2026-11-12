# ScareShift v1.4.2 - Technical Architecture
## System Design & API Migration Roadmap

**Version:** 1.4.2-20250111  
**Purpose:** Technical reference for developers and future API migration

---

## 🏗 SYSTEM ARCHITECTURE

### High-Level Overview
```
┌─────────────────────────────────────────────────────────┐
│                    WordPress Layer                       │
│  - User Authentication                                   │
│  - Session Management                                    │
│  - Content Pages                                         │
│  - Community Features                                    │
└────────────────┬────────────────────────────────────────┘
                 │
┌────────────────▼────────────────────────────────────────┐
│                 REST API Layer (WP)                      │
│  /scareshift/v1/shift/start                             │
│  /scareshift/v1/shift/choose                            │
│  /scareshift/v1/character/list                          │
└────────────────┬────────────────────────────────────────┘
                 │
┌────────────────▼────────────────────────────────────────┐
│              Game State Management                       │
│  - Shift State (user_meta)                              │
│  - Character Data (user_meta)                           │
│  - Scenario Library (options table)                     │
└────────────────┬────────────────────────────────────────┘
                 │
┌────────────────▼────────────────────────────────────────┐
│               Frontend Layer                             │
│  ┌────────────────────────┐ ┌─────────────────────────┐│
│  │   shift-mode.js        │ │  tension-system.js      ││
│  │   - Game Flow          │ │  - Timer Logic          ││
│  │   - UI Rendering       │ │  - Dread/Fatigue       ││
│  │   - API Calls          │ │  - Beat Reveals         ││
│  └────────────────────────┘ └─────────────────────────┘│
└─────────────────────────────────────────────────────────┘
```

---

## 📦 MODULE BREAKDOWN

### 1. tension-system.js (NEW)
**Purpose:** Standalone tension mechanics module  
**Dependencies:** None (pure JavaScript)  
**Size:** ~700 lines

**Key Features:**
```javascript
window.ScareShiftTension = {
    // Timer Management
    startTimer()        // Begin countdown
    stopTimer()         // End countdown
    handleTimeout()     // Penalty application
    
    // State Management
    incrementDread(amount, reason)
    incrementFatigue(amount, reason)
    
    // Visual Effects
    showWarning(message, type)
    applyScreenShake()
    applyFatigueEffects(apply)
    
    // Dramatic Reveals
    showOutcomeInBeats(data, callback)
    showBeat(container, content, delay)
}
```

**API-Ready Design:**
- ✅ No WordPress dependencies
- ✅ No jQuery requirements
- ✅ Accepts config on init
- ✅ Callback-based communication
- ✅ Pure state manipulation

**Migration Path:**
```javascript
// Current (WordPress):
TensionSystem.init({
    gameState: ShiftMode,
    onTimerExpire: () => ShiftMode.handleTimeout()
});

// Future (External API):
TensionSystem.init({
    gameState: gameManager,
    onTimerExpire: () => gameManager.handleTimeout()
});
// No changes needed to tension-system.js itself!
```

---

### 2. shift-mode.js (UPDATED)
**Purpose:** Game flow orchestration  
**Dependencies:** jQuery, tension-system.js  
**Size:** ~600 lines

**Key Responsibilities:**
- Crew selection UI
- Wave/encounter rendering
- Choice submission
- API communication
- State synchronization

**Core Methods:**
```javascript
const ShiftMode = {
    // Initialization
    init()
    
    // Crew Management
    loadCrewSelection()
    renderCrewSelection(characters)
    attachCrewSelection(characters)
    startShift()
    
    // Wave/Encounter Flow
    renderWave()
    renderActions(choices, encounterId)
    attachChoiceHandlers(encounterId)
    
    // Choice Handling
    makeChoice(encounterId, choice)
    handleOutcomeComplete(data)
    
    // Tension Integration
    handleTimerExpire()
    handleDreadChange(newVal, oldVal, reason)
    handleFatigueChange(newVal, oldVal, reason)
}
```

**API Abstraction Points:**
```javascript
// Current API calls:
fetch(SCARESHIFT_SHIFT.restUrl + 'shift/start', {
    headers: {
        'X-WP-Nonce': SCARESHIFT_SHIFT.nonce
    }
})

// Easy to replace with:
fetch(API_CONFIG.baseUrl + '/shift/start', {
    headers: {
        'Authorization': 'Bearer ' + API_CONFIG.token
    }
})
```

---

### 3. class-shift-mode.php
**Purpose:** WordPress backend integration  
**Size:** ~600 lines

**Key Responsibilities:**
- REST API endpoint registration
- User authentication
- Shift state persistence
- Outcome calculation
- Crew stat aggregation

**REST Endpoints:**
```php
POST /scareshift/v1/shift/start
    - Creates new shift
    - Selects scenarios
    - Initializes state
    - Returns first wave data

POST /scareshift/v1/shift/choose
    - Validates choice
    - Calculates outcome
    - Updates state (dread, fatigue, momentum)
    - Returns outcome + next encounter

GET /scareshift/v1/shift/results
    - Retrieves completed shift data
    - Calculates XP earned
    - Returns final stats
```

**State Structure:**
```php
$shift = [
    'shift_id' => uniqid(),
    'user_id' => get_current_user_id(),
    'crew' => [char_id_1, char_id_2],
    'current_wave' => 1,
    'total_waves' => 3,
    'encounters_per_wave' => 8,
    'current_wave_encounters' => 3,
    
    // Tension State
    'dread' => 2,
    'dread_max' => 5,
    'fatigue' => 1,
    'fatigue_max' => 5,
    'momentum' => 5,
    
    // Tracking
    'voice_warmed_up' => true,
    'used_scenarios' => ['enc_1', 'enc_2'],
    
    // Results
    'guests_scared' => 45,
    'incidents_occurred' => 2,
    'incidents_avoided' => 8
];
```

---

## 🔐 SECURITY ARCHITECTURE

### Authentication Flow
```
User Login (WordPress)
    ↓
Session Created
    ↓
Nonce Generated (wp_create_nonce)
    ↓
Localized to JavaScript (SCARESHIFT_SHIFT.nonce)
    ↓
Sent with API requests (X-WP-Nonce header)
    ↓
Verified Server-Side (wp_verify_nonce)
    ↓
Action Authorized
```

### Data Protection

**1. Server Authority:**
```php
// Client sends choice:
{
    "encounter_id": "enc_123",
    "choice": "A"
}

// Server calculates outcome (client can't fake):
$outcome = $this->calculate_outcome($shift, $encounter, $choice);
// Returns guests_scared, dread_change, etc.
```

**2. Input Validation:**
```php
// Verify encounter exists
$encounter = $this->get_encounter($encounter_id);
if (!$encounter) {
    return new WP_Error('invalid_encounter');
}

// Verify choice is valid
if (!isset($encounter['choices'][$choice])) {
    return new WP_Error('invalid_choice');
}

// Verify user owns characters in crew
foreach ($shift['crew'] as $char_id) {
    if (!$this->user_owns_character($user_id, $char_id)) {
        return new WP_Error('unauthorized');
    }
}
```

**3. XSS Prevention:**
```javascript
// All user input escaped before display:
escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Applied to:
// - Character names
// - Scenario text
// - Outcome messages
// - All user-generated content
```

**4. SQL Injection Prevention:**
```php
// Using WordPress DB abstraction:
$wpdb->update(
    $wpdb->usermeta,
    ['meta_value' => serialize($shift)],
    ['user_id' => $user_id, 'meta_key' => 'scareshift_active_shift'],
    ['%s'], // Value format
    ['%d', '%s'] // Where format
);
```

---

## 🔄 STATE MANAGEMENT

### Current Implementation (WordPress)
```php
// State stored in user_meta:
user_meta
├── scareshift_active_shift (serialized shift object)
├── scareshift_characters (array of character objects)
└── scareshift_completed_shifts (history)

// Accessed via:
get_user_meta($user_id, 'scareshift_active_shift', true);
update_user_meta($user_id, 'scareshift_active_shift', $shift);
```

### Advantages:
- ✅ Built-in WordPress auth
- ✅ Automatic cleanup on user delete
- ✅ Easy backups (WP DB export)
- ✅ No additional infrastructure

### Limitations:
- ❌ Not ideal for high concurrency
- ❌ Serialized data (not query-friendly)
- ❌ Tied to WordPress
- ❌ Hard to scale horizontally

---

## 🚀 API MIGRATION PATH

### Phase 1: Hybrid Architecture (Recommended First Step)

Keep WordPress for:
- ✅ User authentication (issue JWT tokens)
- ✅ Marketing website
- ✅ Community forums
- ✅ Character creation UI

Move to API for:
- 🔄 Shift state management
- 🔄 Outcome calculation
- 🔄 Scenario delivery
- 🔄 Analytics/telemetry

**Architecture:**
```
WordPress (JWT Issuer) ←→ External API ←→ Frontend
      ↓                        ↓              ↓
  User DB              Shift State        UI Layer
  Characters           Scenarios          (unchanged)
```

**Implementation:**
```javascript
// 1. User logs in via WordPress
// 2. WordPress issues JWT token
const jwt = await fetch('/wp-json/jwt-auth/v1/token', {
    method: 'POST',
    body: JSON.stringify({ username, password })
});

// 3. Frontend uses JWT for API calls
const response = await fetch('https://api.scareshift.com/v1/shift/start', {
    method: 'POST',
    headers: {
        'Authorization': `Bearer ${jwt.token}`,
        'Content-Type': 'application/json'
    },
    body: JSON.stringify({
        crew: [char1, char2, char3]
    })
});
```

---

### Phase 2: Full API Migration

**New Backend Stack:**
```
Cloudflare Workers (Compute)
    ↓
D1 Database (SQLite) or Supabase (PostgreSQL)
    ↓
Durable Objects (State Management)
```

**Data Model (SQL):**
```sql
-- Users (synced from WordPress or new)
CREATE TABLE users (
    id UUID PRIMARY KEY,
    wp_user_id INT,
    email VARCHAR(255),
    created_at TIMESTAMP
);

-- Characters
CREATE TABLE characters (
    id UUID PRIMARY KEY,
    user_id UUID REFERENCES users(id),
    name VARCHAR(100),
    archetype VARCHAR(50),
    stats JSONB, -- {fear: 7, agility: 5, ...}
    created_at TIMESTAMP
);

-- Shifts (Active and Completed)
CREATE TABLE shifts (
    id UUID PRIMARY KEY,
    user_id UUID REFERENCES users(id),
    crew JSONB, -- [char_id_1, char_id_2]
    state JSONB, -- Current shift state
    status VARCHAR(20), -- 'active', 'completed'
    started_at TIMESTAMP,
    completed_at TIMESTAMP
);

-- Shift Encounters (Event Log)
CREATE TABLE shift_encounters (
    id UUID PRIMARY KEY,
    shift_id UUID REFERENCES shifts(id),
    encounter_id VARCHAR(50),
    choice VARCHAR(10),
    outcome JSONB,
    dread_before INT,
    dread_after INT,
    fatigue_before INT,
    fatigue_after INT,
    created_at TIMESTAMP
);
```

**API Endpoints (Cloudflare Workers):**
```typescript
// POST /v1/shift/start
export async function startShift(request: Request, env: Env) {
    const auth = await validateJWT(request.headers.get('Authorization'));
    const { crew } = await request.json();
    
    // Create shift in DB
    const shift = await env.DB.prepare(`
        INSERT INTO shifts (id, user_id, crew, state, status)
        VALUES (?, ?, ?, ?, 'active')
    `).bind(
        crypto.randomUUID(),
        auth.userId,
        JSON.stringify(crew),
        JSON.stringify(initialState),
    ).run();
    
    // Select scenarios
    const wave = await generateWave(1, crew);
    
    return new Response(JSON.stringify({
        success: true,
        shift: shift,
        wave: wave
    }));
}
```

---

### Migration Checklist

**Step 1: Prepare Data Export**
- [ ] Export user_meta to JSON
- [ ] Export characters to JSON
- [ ] Export shift history to JSON
- [ ] Create migration scripts

**Step 2: Set Up API Infrastructure**
- [ ] Deploy Cloudflare Workers
- [ ] Set up D1 or Supabase database
- [ ] Create tables/schemas
- [ ] Set up JWT validation

**Step 3: Implement API Endpoints**
- [ ] POST /v1/shift/start
- [ ] POST /v1/shift/choose
- [ ] GET /v1/shift/results
- [ ] GET /v1/characters/list
- [ ] POST /v1/characters/create

**Step 4: Update Frontend**
- [ ] Create API client wrapper
- [ ] Replace WordPress REST calls
- [ ] Update authentication flow
- [ ] Test thoroughly

**Step 5: Data Migration**
- [ ] Import users
- [ ] Import characters
- [ ] Import shift history
- [ ] Verify data integrity

**Step 6: Gradual Rollout**
- [ ] Beta test with small user group
- [ ] Monitor for issues
- [ ] Gradual user migration
- [ ] Keep WordPress as fallback

**Step 7: Full Cutover**
- [ ] All users on new API
- [ ] WordPress becomes landing page only
- [ ] Monitor performance
- [ ] Celebrate! 🎉

---

## 📊 PERFORMANCE CONSIDERATIONS

### Current Performance (WordPress)
```
Typical Response Times:
- /shift/start: 200-500ms (scenario selection + state creation)
- /shift/choose: 150-400ms (outcome calculation + state update)
- Total shift: ~30 API calls (3 waves × 8-10 encounters)
```

**Bottlenecks:**
1. Serialized user_meta access (DB read/write each call)
2. PHP array manipulation (slower than compiled code)
3. Scenario selection from options table
4. No caching (everything computed fresh)

### Future Performance (API)
```
Expected Response Times:
- /shift/start: 50-150ms (edge computation + DB query)
- /shift/choose: 30-100ms (serverless function + DB update)
- Total shift: same 30 calls but 3-5x faster
```

**Improvements:**
1. Edge computing (Cloudflare Workers globally distributed)
2. Faster DB queries (indexed, optimized schema)
3. Cached scenario library (CDN or edge KV)
4. Compiled code (Rust/Go vs PHP)
5. WebSocket support for real-time updates (future)

---

## 🔧 DEVELOPMENT WORKFLOW

### Current Setup
```bash
# Local development
1. Install WordPress locally (XAMPP, Local, Docker)
2. Activate plugin
3. Test in browser
4. Check PHP/JS console for errors
5. Iterate

# Deployment
1. FTP/SFTP upload to production
2. Activate plugin
3. Test
```

### Future API Development
```bash
# Local development with Wrangler (Cloudflare CLI)
npm install -g wrangler
wrangler dev  # Local API server on localhost:8787

# Frontend development
npm run dev  # Vite/React dev server

# Testing
npm test  # Unit tests
wrangler tail  # Monitor production logs

# Deployment
wrangler deploy  # Deploy to Cloudflare Workers (seconds)
```

---

## 📈 SCALABILITY ROADMAP

### Current Capacity (WordPress)
- **Concurrent Users:** ~100 (typical shared hosting)
- **DB Load:** Moderate (user_meta queries)
- **Cost:** $5-20/month (shared hosting)

### API Scaling Path

**Phase 1: Small (100-1,000 users)**
```
Cloudflare Workers Free Tier:
- 100,000 requests/day
- ~3,300 shifts/day
- Cost: $0/month
```

**Phase 2: Growing (1,000-10,000 users)**
```
Cloudflare Workers Paid:
- Unlimited requests
- D1 database (10GB free)
- Cost: ~$5-25/month
```

**Phase 3: Established (10,000-100,000 users)**
```
Cloudflare Workers + Supabase:
- Global edge network
- PostgreSQL with pgvector
- Real-time features
- Cost: ~$50-200/month
```

**Phase 4: Large Scale (100,000+ users)**
```
Custom infrastructure:
- Multi-region deployment
- Dedicated databases
- CDN for assets
- Analytics pipeline
- Cost: ~$500+/month
```

---

## 🎯 CONCLUSION

### v1.4.2 Architectural Wins

**For Current WordPress Use:**
- ✅ Clean, modular code
- ✅ Secure by design
- ✅ Performant enough
- ✅ Easy to maintain

**For Future API Migration:**
- ✅ Tension system is portable (no WP dependencies)
- ✅ Frontend can swap backends easily
- ✅ State structure is API-friendly (JSON)
- ✅ Clear separation of concerns
- ✅ Well-documented for handoff

### Next Steps
1. **Ship v1.4.2** - Get user feedback on tension system
2. **Monitor Metrics** - Track engagement, completion rates
3. **Plan API** - When ready, follow migration path above
4. **Scale Gradually** - Hybrid first, full API when needed

---

**The architecture is solid. The game is engaging. The future is bright.** 🎭🚀
