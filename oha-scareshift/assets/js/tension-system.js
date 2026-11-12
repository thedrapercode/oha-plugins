/**
 * ScareShift Tension System
 * v1.4.2
 * 
 * Manages game tension through:
 * - Decision timers
 * - Dynamic dread building
 * - Fatigue accumulation  
 * - Beat-by-beat outcome reveals
 * - Visual state warnings
 */

(function(window) {
    'use strict';
    
    const TensionSystem = {
        // Timer state
        timerInterval: null,
        timeRemaining: 0,
        timerDuration: 20, // seconds (adjustable in settings)
        
        // Tension thresholds
        DREAD_WARNING_THRESHOLD: 4,
        FATIGUE_WARNING_THRESHOLD: 4,
        
        // References to game state
        gameState: null,
        onTimerExpire: null,
        onDreadChange: null,
        onFatigueChange: null,
        
        /**
         * Initialize the tension system
         * @param {Object} config - Configuration object
         */
        init: function(config) {
            this.gameState = config.gameState || null;
            this.onTimerExpire = config.onTimerExpire || function() {};
            this.onDreadChange = config.onDreadChange || function() {};
            this.onFatigueChange = config.onFatigueChange || function() {};
            this.timerDuration = config.timerDuration || 15;
        },
        
        /**
         * Start decision timer for an encounter
         */
        startTimer: function() {
            this.stopTimer(); // Clear any existing timer
            this.timeRemaining = this.timerDuration;
            
            const timerContainer = document.getElementById('scareshift-timer');
            if (!timerContainer) {
                console.error('Timer container not found');
                return;
            }
            
            timerContainer.style.display = 'block';
            this.updateTimerDisplay();
            
            // Check for hesitation dread at intervals
            const dreadCheckpoints = [
                { time: 12, dread: 1 }, // After 8s hesitation
                { time: 6, dread: 1 }   // After 14s hesitation
            ];
            
            this.timerInterval = setInterval(() => {
                this.timeRemaining--;
                this.updateTimerDisplay();
                
                // Check for dread increases
                const checkpoint = dreadCheckpoints.find(cp => cp.time === this.timeRemaining);
                if (checkpoint && this.gameState) {
                    this.incrementDread(checkpoint.dread, 'Hesitation...');
                }
                
                // Handle timeout
                if (this.timeRemaining <= 0) {
                    this.handleTimeout();
                }
            }, 1000);
        },
        
        /**
         * Stop the decision timer
         */
        stopTimer: function() {
            if (this.timerInterval) {
                clearInterval(this.timerInterval);
                this.timerInterval = null;
            }
            
            const timerContainer = document.getElementById('scareshift-timer');
            if (timerContainer) {
                timerContainer.style.display = 'none';
            }
        },
        
        /**
         * Update timer visual display
         */
        updateTimerDisplay: function() {
            const fillEl = document.getElementById('timer-fill');
            const textEl = document.getElementById('timer-text');
            
            if (!fillEl || !textEl) return;
            
            // Calculate percentage
            const percentRemaining = (this.timeRemaining / this.timerDuration) * 100;
            fillEl.style.width = percentRemaining + '%';
            
            // Update text
            textEl.textContent = `⏱ ${this.timeRemaining}s`;
            
            // Update color based on urgency
            fillEl.classList.remove('timer-warning', 'timer-critical');
            if (this.timeRemaining <= 6) {
                fillEl.classList.add('timer-critical');
            } else if (this.timeRemaining <= 12) {
                fillEl.classList.add('timer-warning');
            }
        },
        
        /**
         * Handle timer expiration
         */
        handleTimeout: function() {
            this.stopTimer();
            
            // Apply timeout penalties
            if (this.gameState && this.gameState.wave) {
                this.incrementDread(2, 'Timeout penalty!');
                this.incrementFatigue(1, 'Exhaustion from indecision');
            }
            
            // Trigger callback
            if (typeof this.onTimerExpire === 'function') {
                this.onTimerExpire();
            }
        },
        
        /**
         * Increment dread value and update display
         * @param {number} amount - Amount to increase dread by
         * @param {string} reason - Reason for the increase (optional)
         */
        incrementDread: function(amount, reason) {
            if (!this.gameState || !this.gameState.wave) return;
            
            const maxDread = this.gameState.shift?.dread_max || 5;
            const oldDread = this.gameState.wave.dread || 0;
            const newDread = Math.min(oldDread + amount, maxDread);
            
            // Only update if there's actually a change
            if (newDread === oldDread) return;
            
            this.gameState.wave.dread = newDread;
            
            // Update visual display
            this.updateDreadDisplay(newDread, maxDread, true);
            
            // Show warning if high
            if (newDread >= this.DREAD_WARNING_THRESHOLD) {
                this.showDreadWarning();
            }
            
            // Trigger callback
            if (typeof this.onDreadChange === 'function') {
                this.onDreadChange(newDread, oldDread, reason);
            }
        },
        
        /**
         * Update dread bar display
         * @param {number} value - Current dread value
         * @param {number} max - Maximum dread value
         * @param {boolean} animate - Whether to animate the change
         */
        updateDreadDisplay: function(value, max, animate) {
            const dreadBar = document.getElementById('dread-bar');
            const dreadValue = document.querySelector('.scareshift-state-item:nth-child(1) .scareshift-state-value');
            
            if (!dreadBar) return;
            
            const newPercent = (value / max) * 100;
            dreadBar.style.width = newPercent + '%';
            
            if (animate) {
                dreadBar.classList.add('pulse-once');
                setTimeout(() => dreadBar.classList.remove('pulse-once'), 600);
            }
            
            // Update text value
            if (dreadValue) {
                dreadValue.textContent = `${value}/${max}`;
            }
        },
        
        /**
         * Increment fatigue value and update display
         * @param {number} amount - Amount to increase fatigue by
         * @param {string} reason - Reason for the increase (optional)
         */
        incrementFatigue: function(amount, reason) {
            if (!this.gameState || !this.gameState.wave) return;
            
            const maxFatigue = this.gameState.shift?.fatigue_max || 5;
            const oldFatigue = this.gameState.wave.fatigue || 0;
            const newFatigue = Math.min(oldFatigue + amount, maxFatigue);
            
            // Only update if there's actually a change
            if (newFatigue === oldFatigue) return;
            
            this.gameState.wave.fatigue = newFatigue;
            
            // Update visual display
            this.updateFatigueDisplay(newFatigue, maxFatigue, true);
            
            // Show warning if high
            if (newFatigue >= this.FATIGUE_WARNING_THRESHOLD) {
                this.showFatigueWarning();
            }
            
            // Apply screen effects if exhausted
            if (newFatigue >= this.FATIGUE_WARNING_THRESHOLD) {
                this.applyFatigueEffects(true);
            } else {
                this.applyFatigueEffects(false);
            }
            
            // Trigger callback
            if (typeof this.onFatigueChange === 'function') {
                this.onFatigueChange(newFatigue, oldFatigue, reason);
            }
        },
        
        /**
         * Update fatigue bar display
         * @param {number} value - Current fatigue value
         * @param {number} max - Maximum fatigue value
         * @param {boolean} animate - Whether to animate the change
         */
        updateFatigueDisplay: function(value, max, animate) {
            const fatigueBar = document.getElementById('fatigue-bar');
            const fatigueValue = document.querySelector('.scareshift-state-item:nth-child(3) .scareshift-state-value');
            
            if (!fatigueBar) return;
            
            const newPercent = (value / max) * 100;
            fatigueBar.style.width = newPercent + '%';
            
            if (animate) {
                fatigueBar.classList.add('pulse-once');
                setTimeout(() => fatigueBar.classList.remove('pulse-once'), 600);
            }
            
            // Update text value
            if (fatigueValue) {
                fatigueValue.textContent = `${value}/${max}`;
            }
        },
        
        /**
         * Show warning message for high dread
         */
        showDreadWarning: function() {
            this.showWarning('⚠️ Guests are on edge!', 'dread');
            this.applyScreenShake();
        },
        
        /**
         * Show warning message for high fatigue
         */
        showFatigueWarning: function() {
            this.showWarning('💪 You\'re exhausted!', 'fatigue');
        },
        
        /**
         * Display a warning message
         * @param {string} message - Warning message
         * @param {string} type - Type of warning (dread/fatigue)
         */
        showWarning: function(message, type) {
            // Remove any existing warnings
            const existing = document.querySelector('.scareshift-tension-warning');
            if (existing) existing.remove();
            
            // Create warning element
            const warning = document.createElement('div');
            warning.className = `scareshift-tension-warning scareshift-tension-warning-${type}`;
            warning.textContent = message;
            
            // Insert at top of game container
            const container = document.getElementById('scareshift-shift-mode');
            if (container) {
                container.insertBefore(warning, container.firstChild);
                
                // Remove after 3 seconds
                setTimeout(() => {
                    warning.classList.add('fade-out');
                    setTimeout(() => warning.remove(), 500);
                }, 3000);
            }
        },
        
        /**
         * Apply screen shake effect
         */
        applyScreenShake: function() {
            const container = document.getElementById('scareshift-shift-mode');
            if (!container) return;
            
            container.classList.add('screen-shake');
            setTimeout(() => container.classList.remove('screen-shake'), 500);
        },
        
        /**
         * Apply or remove fatigue visual effects
         * @param {boolean} apply - Whether to apply effects
         */
        applyFatigueEffects: function(apply) {
            const container = document.getElementById('scareshift-shift-mode');
            if (!container) return;
            
            if (apply) {
                container.classList.add('fatigued');
            } else {
                container.classList.remove('fatigued');
            }
        },
        
        /**
         * Show outcome in dramatic beats
         * @param {Object} data - Outcome data from server
         * @param {Function} callback - Callback when complete
         */
        showOutcomeInBeats: async function(data, callback) {
            const container = document.getElementById('scareshift-shift-mode');
            if (!container) return;
            
            // Create outcome container
            const outcomeContainer = document.createElement('div');
            outcomeContainer.className = 'scareshift-outcome-beats';
            container.innerHTML = '';
            container.appendChild(outcomeContainer);
            
            const outcome = data.outcome;
            
            // Beat 1: "Resolving..." (then remove it)
            const resolvingBeat = await this.showBeat(outcomeContainer, `
                <div class="outcome-beat-resolving">
                    <div class="resolving-spinner"></div>
                    <p>Resolving...</p>
                </div>
            `, 800);
            
            // Remove resolving spinner
            if (resolvingBeat) {
                resolvingBeat.style.opacity = '0';
                setTimeout(() => resolvingBeat.remove(), 300);
            }
            
            // Beat 2: Initial reaction
            await this.showBeat(outcomeContainer, `
                <div class="outcome-beat-reaction">
                    ${this.escapeHtml(outcome.message)}
                </div>
            `, 1200);
            
            // Beat 3: Animate state changes
            if (outcome.dread_change || outcome.momentum_change || outcome.fatigue_change) {
                const changes = [];
                if (outcome.dread_change) {
                    changes.push(`😱 Dread ${outcome.dread_change > 0 ? '+' : ''}${outcome.dread_change}`);
                    if (this.gameState && this.gameState.wave) {
                        this.updateDreadDisplay(this.gameState.wave.dread, this.gameState.shift.dread_max, true);
                    }
                }
                if (outcome.momentum_change) {
                    changes.push(`⚡ Momentum ${outcome.momentum_change > 0 ? '+' : ''}${outcome.momentum_change}`);
                }
                if (outcome.fatigue_change) {
                    changes.push(`💪 Fatigue ${outcome.fatigue_change > 0 ? '+' : ''}${outcome.fatigue_change}`);
                    if (this.gameState && this.gameState.wave) {
                        this.updateFatigueDisplay(this.gameState.wave.fatigue, this.gameState.shift.fatigue_max, true);
                    }
                }
                
                await this.showBeat(outcomeContainer, `
                    <div class="outcome-beat-states">
                        ${changes.map(c => `<div class="state-change">${c}</div>`).join('')}
                    </div>
                `, 1000);
            }
            
            // Beat 4: Result
            const resultClass = outcome.success !== false ? 'success' : 'failure';
            await this.showBeat(outcomeContainer, `
                <div class="outcome-beat-result ${resultClass}">
                    <div class="result-icon">${outcome.success !== false ? '✓' : '✗'}</div>
                    <div class="result-text">${outcome.success !== false ? 'SUCCESS' : 'SETBACK'}</div>
                </div>
            `, 800);
            
            // Beat 5: Stats
            await this.showBeat(outcomeContainer, `
                <div class="outcome-beat-stats">
                    <div class="outcome-stat-item">
                        <span class="outcome-stat-label">Guests Scared</span>
                        <span class="outcome-stat-value positive">+${outcome.guests_scared || 0}</span>
                    </div>
                    ${outcome.incidents_occurred > 0 ? `
                        <div class="outcome-stat-item">
                            <span class="outcome-stat-label">Incidents</span>
                            <span class="outcome-stat-value negative">${outcome.incidents_occurred}</span>
                        </div>
                    ` : ''}
                    ${outcome.incidents_avoided > 0 ? `
                        <div class="outcome-stat-item">
                            <span class="outcome-stat-label">Incidents Avoided</span>
                            <span class="outcome-stat-value positive">+${outcome.incidents_avoided}</span>
                        </div>
                    ` : ''}
                </div>
            `, 600);
            
            // Show character growth if any
            const statChanges = outcome.stat_changes || {};
            if (Object.keys(statChanges).length > 0) {
                const changesHtml = Object.entries(statChanges)
                    .filter(([_, change]) => change !== 0)
                    .map(([stat, change]) => {
                        const label = stat.charAt(0).toUpperCase() + stat.slice(1);
                        const className = change > 0 ? 'positive' : 'negative';
                        const displayChange = Math.abs(change) < 0.01 ? change.toFixed(3) : change.toFixed(2);
                        return `
                            <div class="scareshift-stat-change ${className}">
                                ${label}: ${change > 0 ? '+' : ''}${displayChange}
                            </div>
                        `;
                    }).join('');
                
                if (changesHtml) {
                    await this.showBeat(outcomeContainer, `
                        <div class="outcome-beat-growth">
                            <h3>Character Growth</h3>
                            ${changesHtml}
                        </div>
                    `, 600);
                }
            }
            
            // Continue button
            await this.showBeat(outcomeContainer, `
                <div class="outcome-beat-continue">
                    ${data.shift_complete ? 
                        '<button class="scareshift-btn scareshift-btn-primary" id="view-results">View Shift Results</button>' :
                        data.wave_complete ?
                        '<button class="scareshift-btn scareshift-btn-primary" id="next-wave">Next Wave</button>' :
                        '<button class="scareshift-btn scareshift-btn-primary" id="continue">Continue</button>'
                    }
                </div>
            `, 0);
            
            // Trigger callback with data
            if (typeof callback === 'function') {
                callback(data);
            }
        },
        
        /**
         * Show a single beat in the outcome sequence
         * @param {HTMLElement} container - Container element
         * @param {string} content - HTML content for the beat
         * @param {number} delay - Delay before showing next beat (ms)
         * @returns {Promise<HTMLElement>} The created beat element
         */
        showBeat: function(container, content, delay) {
            return new Promise(resolve => {
                const beat = document.createElement('div');
                beat.className = 'outcome-beat';
                beat.innerHTML = content;
                beat.style.opacity = '0';
                container.appendChild(beat);
                
                // Fade in
                setTimeout(() => {
                    beat.style.opacity = '1';
                }, 10);
                
                // Wait before resolving with the element
                setTimeout(() => resolve(beat), delay);
            });
        },
        
        /**
         * Escape HTML to prevent XSS
         * @param {string} text - Text to escape
         * @returns {string} Escaped text
         */
        escapeHtml: function(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },
        
        /**
         * Check and apply fatigue penalty based on encounter count
         * @param {number} encounterCount - Current encounter count
         */
        checkFatiguePenalty: function(encounterCount) {
            // Every 3 encounters adds fatigue
            if (encounterCount > 0 && encounterCount % 3 === 0) {
                this.incrementFatigue(1, 'Physical exhaustion');
            }
        },
        
        /**
         * Check if choice should add fatigue
         * @param {string} choiceText - Text of the chosen action
         */
        checkChoiceFatigue: function(choiceText) {
            if (!choiceText) return;
            
            const text = choiceText.toLowerCase();
            
            // Physical actions
            if (text.includes('jump') || text.includes('chase') || text.includes('run') ||
                text.includes('physical') || text.includes('grab') || text.includes('lunge')) {
                this.incrementFatigue(1, 'Physical exertion');
                return;
            }
            
            // Vocal strain
            if (text.includes('scream') || text.includes('yell') || text.includes('roar') ||
                text.includes('shout') || text.includes('bellow')) {
                this.incrementFatigue(1, 'Vocal strain');
            }
        },
        
        /**
         * Reset tension system for new wave
         */
        reset: function() {
            this.stopTimer();
            this.applyFatigueEffects(false);
        }
    };
    
    // Expose to window
    window.ScareShiftTension = TensionSystem;
    
})(window);
