(function($) {
    'use strict';
    
    const DailyChallenge = {
        scenario: null,
        selectedChoice: null,
        completed: false,
        
        init: function() {
            const container = document.getElementById('scareshift-daily-challenge');
            if (!container) return;
            
            this.loadTodayScenario();
        },
        
        async loadTodayScenario() {
            const container = document.getElementById('scareshift-daily-challenge');
            
            try {
                const response = await fetch(
                    SCARESHIFT_CHALLENGE.restUrl + 'challenge/today',
                    {
                        headers: {
                            'X-WP-Nonce': SCARESHIFT_CHALLENGE.nonce
                        }
                    }
                );
                
                const data = await response.json();
                
                if (!data.success) {
                    this.showError('No scenario available today. Check back tomorrow!');
                    return;
                }
                
                this.scenario = data.scenario;
                this.completed = data.already_completed;
                
                if (this.completed) {
                    this.renderCompletedState(data.completion_data);
                } else {
                    this.renderScenario();
                }
                
            } catch (error) {
                console.error('Failed to load scenario:', error);
                this.showError('Failed to load today\'s scenario. Please try again later.');
            }
        },
        
        renderScenario() {
            const container = document.getElementById('scareshift-daily-challenge');
            
            const html = `
                <div class="scareshift-scenario">
                    <div class="scareshift-scenario-title">
                        <span class="scareshift-scenario-emoji">🎭</span>
                        ${this.escapeHtml(this.scenario.title)}
                    </div>
                    
                    <div class="scareshift-scenario-description">
                        ${this.escapeHtml(this.scenario.description)}
                    </div>
                    
                    <div class="scareshift-choices" id="scareshift-choices">
                        ${this.renderChoices()}
                    </div>
                    
                    <div id="scareshift-outcome" class="scareshift-outcome" style="display:none;"></div>
                </div>
            `;
            
            container.innerHTML = html;
            this.attachChoiceHandlers();
        },
        
        renderChoices() {
            const choices = this.scenario.choices;
            let html = '';
            
            for (const [key, choice] of Object.entries(choices)) {
                html += `
                    <div class="scareshift-choice" data-choice="${key}">
                        <div class="scareshift-choice-label">Choice ${key}</div>
                        <div class="scareshift-choice-text">${this.escapeHtml(choice.text)}</div>
                    </div>
                `;
            }
            
            return html;
        },
        
        attachChoiceHandlers() {
            const choices = document.querySelectorAll('.scareshift-choice');
            choices.forEach(choice => {
                choice.addEventListener('click', () => {
                    this.selectChoice(choice.dataset.choice);
                });
            });
        },
        
        selectChoice(choice) {
            // Remove previous selection
            document.querySelectorAll('.scareshift-choice').forEach(el => {
                el.classList.remove('selected');
            });
            
            // Mark selected
            const selectedElement = document.querySelector(`[data-choice="${choice}"]`);
            selectedElement.classList.add('selected');
            
            this.selectedChoice = choice;
            
            // Submit choice
            this.submitChoice();
        },
        
        async submitChoice() {
            const choices = document.querySelectorAll('.scareshift-choice');
            choices.forEach(el => el.classList.add('disabled'));
            
            try {
                const response = await fetch(
                    SCARESHIFT_CHALLENGE.restUrl + 'challenge/submit',
                    {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-WP-Nonce': SCARESHIFT_CHALLENGE.nonce
                        },
                        body: JSON.stringify({
                            scenario_id: this.scenario.id,
                            choice: this.selectedChoice
                        })
                    }
                );
                
                const data = await response.json();
                
                if (!data.success) {
                    this.showError(data.message || 'Failed to submit choice');
                    return;
                }
                
                this.showOutcome(data);
                
            } catch (error) {
                console.error('Failed to submit choice:', error);
                this.showError('Failed to submit your choice. Please try again.');
            }
        },
        
        showOutcome(data) {
            const outcomeContainer = document.getElementById('scareshift-outcome');
            
            const statsHtml = this.renderStats(data.stats);
            
            const html = `
                <div class="scareshift-outcome-header">✨ Outcome</div>
                
                <div class="scareshift-outcome-text">
                    ${this.escapeHtml(data.outcome)}
                </div>
                
                ${statsHtml}
                
                <div class="scareshift-outcome-xp">
                    <div class="scareshift-xp">
                        <span>+${data.xp_earned} XP</span>
                        <span class="scareshift-text-muted">|</span>
                        <span>Total: ${data.total_xp} XP</span>
                    </div>
                </div>
                
                <div class="scareshift-text-center scareshift-mt-3">
                    <p class="scareshift-text-muted">Come back tomorrow for a new scenario!</p>
                </div>
            `;
            
            outcomeContainer.innerHTML = html;
            outcomeContainer.style.display = 'block';
            
            // Scroll to outcome
            outcomeContainer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        },
        
        renderStats(stats) {
            if (!stats || Object.keys(stats).length === 0) {
                return '';
            }
            
            const statLabels = {
                fear: 'Fear',
                agility: 'Agility',
                endurance: 'Endurance',
                voice: 'Voice',
                presence: 'Presence'
            };
            
            let html = '<div class="scareshift-outcome-stats">';
            
            for (const [stat, value] of Object.entries(stats)) {
                if (value !== 0) {
                    const className = value > 0 ? 'positive' : 'negative';
                    const sign = value > 0 ? '+' : '';
                    html += `
                        <div class="scareshift-outcome-stat ${className}">
                            ${sign}${value} ${statLabels[stat] || stat}
                        </div>
                    `;
                }
            }
            
            html += '</div>';
            return html;
        },
        
        renderCompletedState(completionData) {
            const container = document.getElementById('scareshift-daily-challenge');
            
            const scenario = this.scenario;
            const choice = completionData.choice;
            const choiceData = scenario.choices[choice];
            
            const html = `
                <div class="scareshift-scenario">
                    <div class="scareshift-notice scareshift-notice-success">
                        ✅ You've already completed today's scenario! Come back tomorrow for a new challenge.
                    </div>
                    
                    <div class="scareshift-scenario-title">
                        <span class="scareshift-scenario-emoji">🎭</span>
                        ${this.escapeHtml(scenario.title)}
                    </div>
                    
                    <div class="scareshift-scenario-description">
                        ${this.escapeHtml(scenario.description)}
                    </div>
                    
                    <div class="scareshift-card">
                        <h3>Your Choice</h3>
                        <div class="scareshift-choice-label">Choice ${choice}</div>
                        <div class="scareshift-choice-text">${this.escapeHtml(choiceData.text)}</div>
                    </div>
                    
                    <div class="scareshift-outcome" style="display:block;">
                        <div class="scareshift-outcome-header">✨ Outcome</div>
                        <div class="scareshift-outcome-text">
                            ${this.escapeHtml(choiceData.outcome)}
                        </div>
                        ${this.renderStats(choiceData.stats)}
                        <div class="scareshift-outcome-xp">
                            <div class="scareshift-xp">
                                <span>+${completionData.xp_earned} XP Earned</span>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            container.innerHTML = html;
        },
        
        showError(message) {
            const container = document.getElementById('scareshift-daily-challenge');
            container.innerHTML = `
                <div class="scareshift-notice scareshift-notice-danger">
                    ${this.escapeHtml(message)}
                </div>
            `;
        },
        
        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };
    
    // Initialize when DOM is ready
    $(document).ready(function() {
        DailyChallenge.init();
    });
    
    // Export for potential external use
    window.ScareShift = window.ScareShift || {};
    window.ScareShift.DailyChallenge = DailyChallenge;
    
})(jQuery);
