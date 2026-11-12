(function($) {
    'use strict';
    
    const ShiftMode = {
        shift: null,
        wave: null,
        crew: [],
        tensionSystem: null,
        encounterCount: 0,
        
        init: function() {
            const container = document.getElementById('scareshift-shift-mode');
            if (!container) return;
            
            // Initialize tension system
            if (window.ScareShiftTension) {
                this.tensionSystem = window.ScareShiftTension;
                this.tensionSystem.init({
                    gameState: this,
                    timerDuration: 20, // 20 seconds (adjustable in settings later)
                    onTimerExpire: () => this.handleTimerExpire(),
                    onDreadChange: (newVal, oldVal, reason) => this.handleDreadChange(newVal, oldVal, reason),
                    onFatigueChange: (newVal, oldVal, reason) => this.handleFatigueChange(newVal, oldVal, reason)
                });
            }
            
            this.loadCrewSelection();
        },
        
        async loadCrewSelection() {
            const container = document.getElementById('scareshift-shift-mode');
            
            try {
                // Get user's characters
                const response = await fetch(
                    SCARESHIFT_SHIFT.restUrl + 'character/list',
                    {
                        headers: {
                            'X-WP-Nonce': SCARESHIFT_SHIFT.nonce
                        }
                    }
                );
                
                const data = await response.json();
                
                if (!data.success || !data.characters || data.characters.length === 0) {
                    container.innerHTML = `
                        <div class="scareshift-notice scareshift-notice-warning">
                            <strong>No Characters Found</strong>
                            <p>You need to create at least one character before playing Shift Mode.</p>
                            <p><a href="#" class="scareshift-btn">Create Character</a></p>
                        </div>
                    `;
                    return;
                }
                
                this.renderCrewSelection(data.characters);
                
            } catch (error) {
                console.error('Failed to load characters:', error);
                container.innerHTML = `
                    <div class="scareshift-notice scareshift-notice-danger">
                        Error loading characters. Please try again.
                    </div>
                `;
            }
        },
        
        renderCrewSelection(characters) {
            const container = document.getElementById('scareshift-shift-mode');
            
            const html = `
                <div class="scareshift-shift-setup">
                    <h2>🎭 Assemble Your Crew</h2>
                    <p>Select 1-3 characters for your shift. They'll work together against challenging guests.</p>
                    
                    <div class="scareshift-crew-grid" id="crew-selection">
                        ${characters.map((char, idx) => this.renderCharacterCard(char, idx)).join('')}
                    </div>
                    
                    <div class="scareshift-crew-selected">
                        <h3>Selected Crew (<span id="crew-count">0</span>/3)</h3>
                        <div id="selected-crew" class="scareshift-selected-list"></div>
                    </div>
                    
                    <div class="scareshift-shift-actions">
                        <button class="scareshift-btn scareshift-btn-primary" id="start-shift" disabled>
                            Start Shift
                        </button>
                    </div>
                </div>
            `;
            
            container.innerHTML = html;
            this.attachCrewSelection(characters);
        },
        
        renderCharacterCard(char, idx) {
            const scores = char.scores || {fear: 5, agility: 5, endurance: 5, voice: 5, presence: 5};
            const answers = char.answers || {};
            
            return `
                <div class="scareshift-char-card" data-char-id="${char._id}" data-idx="${idx}">
                    <div class="scareshift-char-header">
                        <h4>${this.escapeHtml(answers.character_name || 'Unnamed Character')}</h4>
                        <span class="scareshift-char-archetype">${this.escapeHtml(answers.archetype || 'Unknown')}</span>
                    </div>
                    <div class="scareshift-char-stats-mini">
                        <span>😱 ${scores.fear}</span>
                        <span>🏃 ${scores.agility}</span>
                        <span>💪 ${scores.endurance}</span>
                        <span>🎤 ${scores.voice}</span>
                        <span>✨ ${scores.presence}</span>
                    </div>
                    <button class="scareshift-btn scareshift-select-char" data-char-id="${char._id}">
                        Select
                    </button>
                </div>
            `;
        },
        
        attachCrewSelection(characters) {
            const buttons = document.querySelectorAll('.scareshift-select-char');
            const selectedList = document.getElementById('selected-crew');
            const countSpan = document.getElementById('crew-count');
            const startButton = document.getElementById('start-shift');
            
            buttons.forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const charId = e.target.dataset.charId;
                    const card = document.querySelector(`[data-char-id="${charId}"]`);
                    
                    if (this.crew.includes(charId)) {
                        // Deselect
                        this.crew = this.crew.filter(id => id !== charId);
                        card.classList.remove('selected');
                        btn.textContent = 'Select';
                    } else {
                        // Select (max 3)
                        if (this.crew.length < 3) {
                            this.crew.push(charId);
                            card.classList.add('selected');
                            btn.textContent = 'Selected ✓';
                        }
                    }
                    
                    // Update UI
                    countSpan.textContent = this.crew.length;
                    startButton.disabled = this.crew.length === 0;
                    
                    // Update selected list
                    const selected = characters.filter(c => this.crew.includes(c._id));
                    selectedList.innerHTML = selected.map(c => {
                        const answers = c.answers || {};
                        return `<div class="scareshift-selected-char">${this.escapeHtml(answers.character_name || 'Unnamed')}</div>`;
                    }).join('');
                });
            });
            
            startButton.addEventListener('click', () => {
                this.startShift();
            });
        },
        
        async startShift() {
            const container = document.getElementById('scareshift-shift-mode');
            container.innerHTML = `
                <div class="scareshift-loading">
                    <div class="scareshift-spinner"></div>
                    <p>Starting your shift...</p>
                </div>
            `;
            
            try {
                const response = await fetch(
                    SCARESHIFT_SHIFT.restUrl + 'shift/start',
                    {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-WP-Nonce': SCARESHIFT_SHIFT.nonce
                        },
                        body: JSON.stringify({
                            characters: this.crew
                        })
                    }
                );
                
                const data = await response.json();
                
                if (!data.success) {
                    throw new Error(data.message || 'Failed to start shift');
                }
                
                this.shift = data.shift;
                this.wave = data.wave;
                this.renderWave();
                
            } catch (error) {
                console.error('Failed to start shift:', error);
                container.innerHTML = `
                    <div class="scareshift-notice scareshift-notice-danger">
                        Failed to start shift: ${error.message}
                    </div>
                `;
            }
        },
        
        renderWave() {
            const container = document.getElementById('scareshift-shift-mode');
            const encounter = this.wave.encounter;
            
            // Calculate bar widths
            const progressPct = ((this.shift.current_wave_encounters || 0) / this.shift.encounters_per_wave) * 100;
            const dreadPct = ((this.wave.dread || 0) / (this.shift.dread_max || 5)) * 100;
            const momentumPct = ((this.wave.momentum || 0) / 10) * 100; // Assume max 10 momentum
            const fatiguePct = ((this.wave.fatigue || 0) / (this.shift.fatigue_max || 5)) * 100;
            
            const html = `
                <div class="scareshift-shift-play">
                    <!-- Scene Header with Wave Info -->
                    <div class="scareshift-scene-header">
                        <div class="scareshift-wave-title">
                            <h2>🌫️ Wave ${this.wave.wave_number} of ${this.shift.total_waves}</h2>
                            <span class="scareshift-wave-subtitle">
                                ${this.getDifficultyLabel(encounter.difficulty)}
                            </span>
                        </div>
                        
                        <!-- Wave Progress Bar -->
                        ${this.shift.encounters_per_wave ? `
                            <div class="scareshift-progress-bar-container">
                                <div class="scareshift-progress-bar">
                                    <div class="scareshift-progress-fill" style="width: ${progressPct}%"></div>
                                </div>
                                <span class="scareshift-progress-label">
                                    ${this.shift.current_wave_encounters || 0} / ${this.shift.encounters_per_wave} encounters
                                </span>
                            </div>
                        ` : ''}
                    </div>
                    
                    <!-- Decision Timer -->
                    <div class="scareshift-timer-container" id="scareshift-timer" style="display: none;">
                        <div class="scareshift-timer-label">Decision Time:</div>
                        <div class="scareshift-timer-bar">
                            <div class="scareshift-timer-fill" id="timer-fill"></div>
                        </div>
                        <div class="scareshift-timer-text" id="timer-text">⏱ 20s</div>
                        <div class="scareshift-timer-progress" id="timer-progress">
                            Encounter ${this.shift.current_wave_encounters || 0} / ${this.shift.encounters_per_wave || '?'}
                        </div>
                    </div>
                    
                    <!-- Scene State Panel (THE BIG ADDITION) -->
                    <div class="scareshift-scene-state">
                        <div class="scareshift-state-grid">
                            <div class="scareshift-state-item">
                                <div class="scareshift-state-label">😱 Dread</div>
                                <div class="scareshift-state-bar">
                                    <div class="scareshift-state-fill dread-fill" id="dread-bar" style="width: ${dreadPct}%"></div>
                                </div>
                                <div class="scareshift-state-value">${this.wave.dread || 0}/${this.shift.dread_max || 5}</div>
                            </div>
                            
                            <div class="scareshift-state-item">
                                <div class="scareshift-state-label">⚡ Momentum</div>
                                <div class="scareshift-state-bar">
                                    <div class="scareshift-state-fill momentum-fill" id="momentum-bar" style="width: ${momentumPct}%"></div>
                                </div>
                                <div class="scareshift-state-value">${this.wave.momentum || 0}</div>
                            </div>
                            
                            <div class="scareshift-state-item">
                                <div class="scareshift-state-label">💪 Fatigue</div>
                                <div class="scareshift-state-bar">
                                    <div class="scareshift-state-fill fatigue-fill" id="fatigue-bar" style="width: ${fatiguePct}%"></div>
                                </div>
                                <div class="scareshift-state-value">${this.wave.fatigue || 0}/${this.shift.fatigue_max || 5}</div>
                            </div>
                            
                            <div class="scareshift-state-item">
                                <div class="scareshift-state-label">🎤 Voice</div>
                                <div class="scareshift-state-value ${this.shift.voice_warmed_up ? 'voice-ready' : 'voice-cold'}">
                                    ${this.shift.voice_warmed_up ? 'Warmed Up ✓' : 'Cold'}
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Crew Stats Display -->
                    ${this.wave.crew_stats && this.wave.crew_stats.crew_size > 0 ? `
                        <div class="scareshift-crew-display">
                            <h4>🎭 Your Crew (${this.wave.crew_stats.crew_size} member${this.wave.crew_stats.crew_size > 1 ? 's' : ''})</h4>
                            <div class="scareshift-crew-stats-grid">
                                <span>😱 Fear: ${this.wave.crew_stats.fear.toFixed(1)}</span>
                                <span>🏃 Agility: ${this.wave.crew_stats.agility.toFixed(1)}</span>
                                <span>💪 Endurance: ${this.wave.crew_stats.endurance.toFixed(1)}</span>
                                <span>🎤 Voice: ${this.wave.crew_stats.voice.toFixed(1)}</span>
                                <span>✨ Presence: ${this.wave.crew_stats.presence.toFixed(1)}</span>
                            </div>
                        </div>
                    ` : ''}
                    
                    <!-- Encounter Display -->
                    <div class="scareshift-encounter">
                        <div class="scareshift-encounter-header">
                            <h3>${this.escapeHtml(encounter.title)}</h3>
                        </div>
                        
                        <div class="scareshift-encounter-description">
                            ${this.escapeHtml(encounter.description)}
                        </div>
                        
                        <!-- Action Choices (Redesigned as Actions) -->
                        <div class="scareshift-actions">
                            <div class="scareshift-actions-header">Choose Your Approach:</div>
                            ${this.renderActions(encounter.choices, encounter.id)}
                        </div>
                    </div>
                </div>
            `;
            
            container.innerHTML = html;
            this.attachChoiceHandlers(encounter.id);
            
            // Start decision timer
            if (this.tensionSystem) {
                this.tensionSystem.startTimer();
            }
            
            // Update progress counter
            this.updateProgressCounter();
            
            // Check for fatigue penalty every 3 encounters
            this.encounterCount++;
            if (this.tensionSystem) {
                this.tensionSystem.checkFatiguePenalty(this.encounterCount);
            }
        },
        
        updateProgressCounter() {
            const progressEl = document.getElementById('timer-progress');
            if (progressEl && this.shift) {
                const current = this.shift.current_wave_encounters || 0;
                const total = this.shift.encounters_per_wave || '?';
                progressEl.textContent = `Encounter ${current} / ${total}`;
            }
        },
        
        renderActions(choices, encounterId) {
            let html = '';
            
            const actionLabels = {
                'A': 'Primary',
                'B': 'Alternative',
                'C': 'Risky',
                'D': 'Setup',
                'E': 'Defensive'
            };
            
            for (const [key, choice] of Object.entries(choices)) {
                const label = actionLabels[key] || key;
                html += `
                    <div class="scareshift-action" data-choice="${key}" data-encounter="${encounterId}">
                        <div class="scareshift-action-header">
                            <span class="scareshift-action-label">▶ ${label}</span>
                        </div>
                        <div class="scareshift-action-text">${this.escapeHtml(choice.text)}</div>
                    </div>
                `;
            }
            
            return html;
        },
        
        attachChoiceHandlers(encounterId) {
            const actions = document.querySelectorAll('.scareshift-action');
            
            actions.forEach(action => {
                action.addEventListener('click', () => {
                    // Mark selected
                    actions.forEach(a => a.classList.remove('selected'));
                    action.classList.add('selected');
                    
                    const choiceKey = action.dataset.choice;
                    this.makeChoice(encounterId, choiceKey);
                });
            });
        },
        
        async makeChoice(encounterId, choice) {
            const actions = document.querySelectorAll('.scareshift-action');
            actions.forEach(a => a.classList.add('disabled'));
            
            // Stop the timer
            if (this.tensionSystem) {
                this.tensionSystem.stopTimer();
            }
            
            // Check if choice causes fatigue
            const selectedAction = document.querySelector('.scareshift-action.selected');
            if (selectedAction && this.tensionSystem) {
                const choiceText = selectedAction.querySelector('.scareshift-action-text')?.textContent || '';
                this.tensionSystem.checkChoiceFatigue(choiceText);
            }
            
            try {
                const response = await fetch(
                    SCARESHIFT_SHIFT.restUrl + 'shift/choose',
                    {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-WP-Nonce': SCARESHIFT_SHIFT.nonce
                        },
                        body: JSON.stringify({
                            encounter_id: encounterId,
                            choice: choice
                        })
                    }
                );
                
                const data = await response.json();
                
                if (!data.success) {
                    throw new Error(data.message || 'Failed to submit choice');
                }
                
                // Update game state with response
                if (data.outcome) {
                    // Sync dread/fatigue from server if provided
                    if (data.outcome.dread !== undefined && this.wave) {
                        this.wave.dread = data.outcome.dread;
                    }
                    if (data.outcome.fatigue !== undefined && this.wave) {
                        this.wave.fatigue = data.outcome.fatigue;
                    }
                    if (data.outcome.momentum !== undefined && this.wave) {
                        this.wave.momentum = data.outcome.momentum;
                    }
                }
                
                // Show outcome with beat reveals
                if (this.tensionSystem) {
                    this.tensionSystem.showOutcomeInBeats(data, (resultData) => {
                        this.handleOutcomeComplete(resultData);
                    });
                } else {
                    // Fallback to old method if tension system not available
                    this.showOutcome(data);
                }
                
            } catch (error) {
                console.error('Failed to make choice:', error);
                alert('Error: ' + error.message);
            }
        },
        
        handleTimerExpire() {
            // Timer ran out - this is a FAILURE outcome
            console.log('TIMEOUT - Processing failure with backend');
            
            // Get first action's encounter ID to send timeout to backend
            const actions = document.querySelectorAll('.scareshift-action');
            if (actions.length === 0) return;
            
            const firstAction = actions[0];
            const encounterId = firstAction.dataset.encounter;
            
            // Disable all actions and mark as timeout
            actions.forEach(a => a.classList.add('disabled', 'timeout-selection'));
            
            // Call backend with timeout flag (choice = 'TIMEOUT')
            this.makeChoice(encounterId, 'TIMEOUT');
        },
        
        handleDreadChange(newVal, oldVal, reason) {
            // Optional: Add any additional logic when dread changes
            console.log(`Dread: ${oldVal} → ${newVal}${reason ? ` (${reason})` : ''}`);
        },
        
        handleFatigueChange(newVal, oldVal, reason) {
            // Optional: Add any additional logic when fatigue changes
            console.log(`Fatigue: ${oldVal} → ${newVal}${reason ? ` (${reason})` : ''}`);
        },
        
        handleOutcomeComplete(data) {
            // Handle continuing after outcome reveals complete
            if (data.shift_complete) {
                document.getElementById('view-results').addEventListener('click', () => {
                    this.showResults(data.results);
                });
            } else if (data.wave_complete) {
                this.wave = data.next_wave;
                this.shift = data.shift || this.shift;
                document.getElementById('next-wave').addEventListener('click', () => {
                    this.encounterCount = 0; // Reset counter for new wave
                    if (this.tensionSystem) {
                        this.tensionSystem.reset();
                    }
                    this.renderWave();
                });
            } else {
                // Update shift progress
                if (data.progress) {
                    this.shift.current_wave_encounters = data.progress.encounters_this_wave;
                    this.shift.encounters_per_wave = data.progress.encounters_target;
                }
                // Update wave with new encounter
                if (data.next_encounter) {
                    this.wave = data.next_encounter;
                }
                document.getElementById('continue').addEventListener('click', () => {
                    this.renderWave();
                });
            }
            
            // Update progress counter if it exists
            this.updateProgressCounter();
        },
        
        showOutcome(data) {
            const container = document.getElementById('scareshift-shift-mode');
            
            const outcome = data.outcome;
            const statChanges = outcome.stat_changes || {};
            
            let html = `
                <div class="scareshift-outcome-screen">
                    <h2>Outcome</h2>
                    <div class="scareshift-outcome-message">
                        ${this.escapeHtml(outcome.message)}
                    </div>
                    
                    <div class="scareshift-outcome-results">
                        <div class="scareshift-outcome-stat">
                            <span class="scareshift-outcome-label">Guests Scared</span>
                            <span class="scareshift-outcome-value positive">+${outcome.guests_scared}</span>
                        </div>
                        ${outcome.incidents_occurred > 0 ? `
                        <div class="scareshift-outcome-stat">
                            <span class="scareshift-outcome-label">Incidents</span>
                            <span class="scareshift-outcome-value negative">${outcome.incidents_occurred}</span>
                        </div>
                        ` : ''}
                        ${outcome.incidents_avoided > 0 ? `
                        <div class="scareshift-outcome-stat">
                            <span class="scareshift-outcome-label">Incidents Avoided</span>
                            <span class="scareshift-outcome-value positive">+${outcome.incidents_avoided}</span>
                        </div>
                        ` : ''}
                    </div>
                    
                    ${Object.keys(statChanges).length > 0 ? `
                    <div class="scareshift-stat-changes">
                        <h3>Character Growth</h3>
                        ${Object.entries(statChanges).map(([stat, change]) => {
                            if (change === 0) return '';
                            const label = stat.charAt(0).toUpperCase() + stat.slice(1);
                            const className = change > 0 ? 'positive' : 'negative';
                            const displayChange = Math.abs(change) < 0.01 ? change.toFixed(3) : change.toFixed(2);
                            return `
                                <div class="scareshift-stat-change ${className}">
                                    ${label}: ${change > 0 ? '+' : ''}${displayChange}
                                </div>
                            `;
                        }).join('')}
                    </div>
                    ` : ''}
                    
                    <div class="scareshift-continue">
                        ${data.shift_complete ? 
                            '<button class="scareshift-btn scareshift-btn-primary" id="view-results">View Shift Results</button>' :
                            data.wave_complete ?
                            '<button class="scareshift-btn scareshift-btn-primary" id="next-wave">Next Wave</button>' :
                            '<button class="scareshift-btn scareshift-btn-primary" id="continue">Continue</button>'
                        }
                    </div>
                </div>
            `;
            
            container.innerHTML = html;
            
            if (data.shift_complete) {
                document.getElementById('view-results').addEventListener('click', () => {
                    this.showResults(data.results);
                });
            } else if (data.wave_complete) {
                this.wave = data.next_wave;
                this.shift = data.shift || this.shift;
                document.getElementById('next-wave').addEventListener('click', () => {
                    this.renderWave();
                });
            } else {
                // Update shift progress
                if (data.progress) {
                    this.shift.current_wave_encounters = data.progress.encounters_this_wave;
                    this.shift.encounters_per_wave = data.progress.encounters_target;
                }
                // Update wave with new encounter
                if (data.next_encounter) {
                    this.wave = data.next_encounter;
                }
                document.getElementById('continue').addEventListener('click', () => {
                    this.renderWave();
                });
            }
        },
        
        showResults(results) {
            const container = document.getElementById('scareshift-shift-mode');
            
            const stars = '⭐'.repeat(results.stars);
            const runNumber = results.run_number || 1;
            const canContinue = results.can_continue || false;
            const nextRunWaves = runNumber + 1;
            
            const html = `
                <div class="scareshift-results-screen">
                    <h2>Shift Complete!</h2>
                    <div class="scareshift-stars">${stars}</div>
                    
                    <div class="scareshift-results-grid">
                        <div class="scareshift-result-card">
                            <div class="scareshift-result-value">${results.guests_scared}</div>
                            <div class="scareshift-result-label">Guests Scared</div>
                        </div>
                        <div class="scareshift-result-card">
                            <div class="scareshift-result-value">${results.incidents_avoided}</div>
                            <div class="scareshift-result-label">Incidents Avoided</div>
                        </div>
                        <div class="scareshift-result-card">
                            <div class="scareshift-result-value">${results.incidents_occurred}</div>
                            <div class="scareshift-result-label">Incidents Occurred</div>
                        </div>
                    </div>
                    
                    <div class="scareshift-xp-earned">
                        <span class="scareshift-xp-label">XP Earned:</span>
                        <span class="scareshift-xp-value">+${results.xp_earned}</span>
                    </div>
                    
                    <div class="scareshift-total-xp">
                        Total XP: ${results.total_xp}
                    </div>
                    
                    <!-- Mini-Game Placeholder -->
                    <div class="scareshift-minigame-placeholder">
                        <h3>🎮 Mini-Game</h3>
                        <div class="minigame-content">
                            <p>Quick-time event coming soon!</p>
                            <p class="minigame-subtitle">Test your haunt skills in a bonus challenge</p>
                        </div>
                    </div>
                    
                    <!-- Progression Actions -->
                    <div class="scareshift-results-actions">
                        ${canContinue && nextRunWaves <= 3 ? `
                            <button class="scareshift-btn scareshift-btn-success" id="continue-crew">
                                ▶ Continue with Crew (${nextRunWaves} Wave${nextRunWaves > 1 ? 's' : ''})
                            </button>
                        ` : ''}
                        <button class="scareshift-btn scareshift-btn-primary" id="new-shift">Return to Start</button>
                    </div>
                    
                    ${canContinue && nextRunWaves <= 3 ? `
                        <div class="scareshift-progression-note">
                            💡 Continue to face ${nextRunWaves} waves with the same crew
                            ${nextRunWaves >= 2 ? ' | Timer reduced by 5s | Higher difficulty!' : ''}
                        </div>
                    ` : ''}
                </div>
            `;
            
            container.innerHTML = html;
            
            // Handle new shift button
            document.getElementById('new-shift').addEventListener('click', () => {
                this.crew = [];
                this.shift = null;
                this.wave = null;
                this.loadCrewSelection();
            });
            
            // Handle continue with crew button
            const continueBtn = document.getElementById('continue-crew');
            if (continueBtn) {
                continueBtn.addEventListener('click', async () => {
                    await this.continueWithCrew(results);
                });
            }
        },
        
        async continueWithCrew(previousResults) {
            const runNumber = (previousResults.run_number || 1) + 1;
            const totalWaves = Math.min(runNumber, 3); // Max 3 waves
            
            // Reduce timer for run 2+
            if (runNumber >= 2 && this.tensionSystem) {
                this.tensionSystem.timerDuration = 15; // 20s - 5s = 15s
            }
            
            try {
                const response = await fetch(
                    SCARESHIFT_SHIFT.restUrl + 'shift/start',
                    {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-WP-Nonce': SCARESHIFT_SHIFT.nonce
                        },
                        body: JSON.stringify({
                            characters: this.crew.map(c => c._id),
                            run_number: runNumber,
                            total_waves: totalWaves
                        })
                    }
                );
                
                const data = await response.json();
                
                if (data.success) {
                    this.shift = data.shift;
                    this.wave = data.wave;
                    this.encounterCount = 0;
                    this.renderWave();
                } else {
                    alert('Failed to start continued shift: ' + (data.message || 'Unknown error'));
                }
            } catch (error) {
                console.error('Error continuing shift:', error);
                alert('Error continuing shift. Please try again.');
            }
        },
        
        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },
        
        getDifficultyClass(difficulty) {
            const map = {
                'standard_crowd': 'green',
                'tough_crowd': 'yellow',
                'hot_situation': 'orange',
                'all_hands': 'red'
            };
            return map[difficulty] || 'green';
        },
        
        getDifficultyLabel(difficulty) {
            const labels = {
                'standard_crowd': '🟢 Standard Crowd',
                'tough_crowd': '🟡 Tough Crowd',
                'hot_situation': '🟠 Hot Situation',
                'all_hands': '🔴 All Hands'
            };
            return labels[difficulty] || difficulty.toUpperCase();
        }
    };
    
    // Initialize
    $(document).ready(function() {
        ShiftMode.init();
    });
    
    window.ScareShift = window.ScareShift || {};
    window.ScareShift.ShiftMode = ShiftMode;
    
})(jQuery);
