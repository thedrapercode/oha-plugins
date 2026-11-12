    // ==================== NEW SCENARIOS - Technical Challenges ====================
    
    [
        'difficulty' => 'tough_crowd',
        'title' => 'Broken Audio Cue',
        'description' => 'Your sound cue fails to trigger as a group enters your scene. The silence where a screech should be is deafening.',
        'choices' => [
            'A' => [
                'text' => 'Replace the cue with a physical movement scare',
                'outcome' => 'You compensate with strong body language. The sudden motion gets them!',
                'guests_scared' => 5,
                'incidents' => 0,
                'momentum' => 2,
                'stat_changes' => ['agility' => 0.03, 'presence' => 0.02]
            ],
            'B' => [
                'text' => 'Use your voice to recreate the effect',
                'outcome' => 'Your vocal control saves the moment. That guttural sound was perfect!',
                'guests_scared' => 6,
                'incidents' => 0,
                'momentum' => 2,
                'stat_changes' => ['voice' => 0.04]
            ],
            'C' => [
                'text' => 'Wait for the next group and reset',
                'outcome' => 'You protect the scene but lose momentum. This group walks through unscathed.',
                'guests_scared' => 0,
                'incidents' => 0,
                'momentum' => -2,
                'stat_changes' => ['endurance' => 0.02]
            ]
        ]
    ],
    
    [
        'difficulty' => 'standard_crowd',
        'title' => 'Guest Makes Eye Contact',
        'description' => 'One guest locks eyes with you and clearly knows where you are. They\'re watching your every move.',
        'choices' => [
            'A' => [
                'text' => 'Hold eye contact and slowly advance',
                'outcome' => 'The tension becomes unbearable. They break first and jump back!',
                'guests_scared' => 7,
                'incidents' => 0,
                'momentum' => 3,
                'stat_changes' => ['presence' => 0.04, 'fear' => 0.03]
            ],
            'B' => [
                'text' => 'Break line of sight and reposition',
                'outcome' => 'You reset the scare on your terms. They lose track and you strike from the shadows!',
                'guests_scared' => 6,
                'incidents' => 0,
                'momentum' => 2,
                'stat_changes' => ['agility' => 0.03, 'timing' => 0.02]
            ],
            'C' => [
                'text' => 'Jump immediately to force the scare',
                'outcome' => 'The scare lands, but feels rushed. They saw it coming.',
                'guests_scared' => 4,
                'incidents' => 0,
                'momentum' => 0,
                'stat_changes' => ['fear' => 0.02]
            ]
        ]
    ],
    
    [
        'difficulty' => 'tough_crowd',
        'title' => 'Energy Crash',
        'description' => 'Late in the night, you feel your energy dipping fast. Your body is screaming for rest.',
        'choices' => [
            'A' => [
                'text' => 'Scale back intensity and focus on timing',
                'outcome' => 'You finish the shift strong and controlled. Quality over quantity!',
                'guests_scared' => 5,
                'incidents' => 0,
                'momentum' => 2,
                'stat_changes' => ['endurance' => 0.04, 'timing' => 0.03]
            ],
            'B' => [
                'text' => 'Push harder to finish the night',
                'outcome' => 'You burn bright, but fatigue sets in. The last few scares are sloppy.',
                'guests_scared' => 7,
                'incidents' => 0,
                'momentum' => 1,
                'stat_changes' => ['fear' => 0.03, 'endurance' => -0.03]
            ],
            'C' => [
                'text' => 'Request a rotation or brief reset',
                'outcome' => 'You recover without compromising performance. Smart choice!',
                'guests_scared' => 4,
                'incidents' => 0,
                'momentum' => 1,
                'stat_changes' => ['endurance' => 0.05]
            ]
        ]
    ],
    
    [
        'difficulty' => 'standard_crowd',
        'title' => 'Guest Drops an Item',
        'description' => 'A phone or personal item falls to the floor mid-scare. They stop and bend down to retrieve it.',
        'choices' => [
            'A' => [
                'text' => 'Freeze and loom silently until they retrieve it',
                'outcome' => 'The moment becomes deeply uncomfortable. They grab it and RUN!',
                'guests_scared' => 6,
                'incidents' => 0,
                'momentum' => 2,
                'stat_changes' => ['presence' => 0.04, 'timing' => 0.02]
            ],
            'B' => [
                'text' => 'Break off and let them pass',
                'outcome' => 'You prioritize safety and flow. Professional handling!',
                'guests_scared' => 2,
                'incidents' => 0,
                'momentum' => 0,
                'stat_changes' => ['presence' => 0.02]
            ],
            'C' => [
                'text' => 'Escalate the scare to rush them through',
                'outcome' => 'The tension collapses into confusion. They\'re just trying to get their phone.',
                'guests_scared' => 3,
                'incidents' => 0,
                'momentum' => -1,
                'stat_changes' => ['fear' => -0.02]
            ]
        ]
    ],
    
    [
        'difficulty' => 'tough_crowd',
        'title' => 'Overcrowded Scene',
        'description' => 'Too many guests enter your room at once, killing spacing. You can barely move without bumping into someone.',
        'choices' => [
            'A' => [
                'text' => 'Focus on controlling the room instead of jump scares',
                'outcome' => 'You regain authority over the space. Your presence fills the room!',
                'guests_scared' => 8,
                'incidents' => 0,
                'momentum' => 3,
                'stat_changes' => ['presence' => 0.05, 'voice' => 0.02]
            ],
            'B' => [
                'text' => 'Target one guest and ignore the rest',
                'outcome' => 'One scare lands hard, others miss it. Half the room is confused.',
                'guests_scared' => 4,
                'incidents' => 0,
                'momentum' => 0,
                'stat_changes' => ['fear' => 0.02]
            ],
            'C' => [
                'text' => 'Fall back and let the crowd thin',
                'outcome' => 'You preserve future scares. The next group will get the full experience.',
                'guests_scared' => 2,
                'incidents' => 0,
                'momentum' => 1,
                'stat_changes' => ['timing' => 0.03]
            ]
        ]
    ],
    
    [
        'difficulty' => 'tough_crowd',
        'title' => 'Prop Malfunction Noise',
        'description' => 'A prop begins making an unintended repetitive sound. *Click-click-click-click* - it\'s breaking the atmosphere.',
        'choices' => [
            'A' => [
                'text' => 'Incorporate the sound into your character',
                'outcome' => 'The glitch becomes unsettling. You move in rhythm with the clicks - creepy!',
                'guests_scared' => 7,
                'incidents' => 0,
                'momentum' => 3,
                'stat_changes' => ['presence' => 0.04, 'fear' => 0.03]
            ],
            'B' => [
                'text' => 'Physically reposition away from the sound',
                'outcome' => 'You reduce distraction but lose range. Your scare zone just got smaller.',
                'guests_scared' => 4,
                'incidents' => 0,
                'momentum' => 0,
                'stat_changes' => ['agility' => 0.02]
            ],
            'C' => [
                'text' => 'Signal tech support and adjust performance',
                'outcome' => 'You adapt professionally. Tech will fix it but you keep the scares going.',
                'guests_scared' => 5,
                'incidents' => 0,
                'momentum' => 1,
                'stat_changes' => ['presence' => 0.03]
            ]
        ]
    ],
