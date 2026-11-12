<?php
/**
 * Scenario Library - Comprehensive collection of haunt encounters
 * 
 * Each scenario has:
 * - difficulty: standard_crowd, tough_crowd, hot_situation, all_hands
 * - title: Scenario name (shown to player)
 * - description: What's happening (no labels that give away answer)
 * - choices: Variable (3-7 options)
 * - Each choice has: text, outcome, guests_scared, incidents, momentum, stat_changes
 */

if (!defined('ABSPATH')) exit;

return [
    
    // ==================== STANDARD CROWD (Green - Easy) ====================
    
    [
        'difficulty' => 'standard_crowd',
        'title' => 'Opening Night Energy',
        'description' => 'The first group of the night enters. They\'re laughing, taking photos, clearly excited. Perfect energy.',
        'choices' => [
            'A' => [
                'text' => 'Hit them with your signature scare right away',
                'outcome' => 'Perfect timing! They scream and laugh. Great way to start the night.',
                'guests_scared' => 5,
                'incidents' => 0,
                'momentum' => 2,
                'stat_changes' => ['fear' => 0.03]
            ],
            'B' => [
                'text' => 'Build atmosphere slowly, let tension grow',
                'outcome' => 'Beautiful execution! The slow burn pays off when you reveal yourself.',
                'guests_scared' => 6,
                'incidents' => 0,
                'momentum' => 2,
                'stat_changes' => ['presence' => 0.04, 'fear' => 0.02]
            ],
            'C' => [
                'text' => 'Work with the other actors for a coordinated sequence',
                'outcome' => 'Teamwork! The synchronized scares get huge reactions.',
                'guests_scared' => 7,
                'incidents' => 0,
                'momentum' => 3,
                'stat_changes' => ['presence' => 0.03, 'endurance' => 0.02]
            ]
        ]
    ],
    
    [
        'difficulty' => 'standard_crowd',
        'title' => 'Date Night',
        'description' => 'A young couple enters, holding hands. One is nervous, the other is trying to be brave and protective.',
        'choices' => [
            'A' => [
                'text' => 'Focus your scare on the nervous one',
                'outcome' => 'They jump, grab their partner. Classic! Everyone\'s happy.',
                'guests_scared' => 4,
                'incidents' => 0,
                'momentum' => 1,
                'stat_changes' => ['fear' => 0.02]
            ],
            'B' => [
                'text' => 'Surprise the "brave" one instead',
                'outcome' => 'Brilliant reversal! Their partner laughs. Both enjoy it.',
                'guests_scared' => 5,
                'incidents' => 0,
                'momentum' => 2,
                'stat_changes' => ['fear' => 0.03, 'presence' => 0.02]
            ],
            'C' => [
                'text' => 'Get them both at the same time from different angles',
                'outcome' => 'Perfect coordination! They both scream, then laugh together.',
                'guests_scared' => 6,
                'incidents' => 0,
                'momentum' => 2,
                'stat_changes' => ['agility' => 0.03, 'fear' => 0.02]
            ],
            'D' => [
                'text' => 'Read their comfort level and adjust intensity',
                'outcome' => 'Professional! You give them just the right amount of scare.',
                'guests_scared' => 5,
                'incidents' => 0,
                'momentum' => 1,
                'stat_changes' => ['presence' => 0.04]
            ]
        ]
    ],
    
    [
        'difficulty' => 'standard_crowd',
        'title' => 'Horror Fans',
        'description' => 'A group wearing horror movie t-shirts enters. They\'re analyzing everything, looking for easter eggs.',
        'choices' => [
            'A' => [
                'text' => 'Give them a classic haunted house scare',
                'outcome' => 'They appreciate it but you can tell they\'ve seen it before.',
                'guests_scared' => 3,
                'incidents' => 0,
                'momentum' => 0,
                'stat_changes' => []
            ],
            'B' => [
                'text' => 'Try something creative and unexpected',
                'outcome' => 'Yes! They didn\'t see that coming. You got the horror fans!',
                'guests_scared' => 6,
                'incidents' => 0,
                'momentum' => 3,
                'stat_changes' => ['fear' => 0.04, 'presence' => 0.03]
            ],
            'C' => [
                'text' => 'Reference something from their favorite films',
                'outcome' => 'They love it! Recognition plus scare equals perfect combo.',
                'guests_scared' => 5,
                'incidents' => 0,
                'momentum' => 2,
                'stat_changes' => ['presence' => 0.04, 'voice' => 0.02]
            ],
            'D' => [
                'text' => 'Play to their knowledge - make it a game',
                'outcome' => 'Smart! They engage more and enjoy the interactive element.',
                'guests_scared' => 4,
                'incidents' => 0,
                'momentum' => 2,
                'stat_changes' => ['presence' => 0.03]
            ]
        ]
    ],
    
    [
        'difficulty' => 'standard_crowd',
        'title' => 'Family with Teens',
        'description' => 'Parents with teenagers. The kids are trying to act tough, parents are more genuinely nervous.',
        'choices' => [
            'A' => [
                'text' => 'Scare the parents to show the teens it\'s okay',
                'outcome' => 'Good strategy! Parents scream, kids laugh, everyone relaxes.',
                'guests_scared' => 5,
                'incidents' => 0,
                'momentum' => 1,
                'stat_changes' => ['fear' => 0.02, 'presence' => 0.02]
            ],
            'B' => [
                'text' => 'Challenge the teens directly',
                'outcome' => 'They try to stay cool but you catch them off guard. Nice!',
                'guests_scared' => 5,
                'incidents' => 0,
                'momentum' => 2,
                'stat_changes' => ['fear' => 0.03]
            ],
            'C' => [
                'text' => 'Get everyone at once with a big set piece',
                'outcome' => 'Family bonding through terror! Everyone screams, everyone laughs.',
                'guests_scared' => 6,
                'incidents' => 0,
                'momentum' => 2,
                'stat_changes' => ['fear' => 0.02, 'presence' => 0.03]
            ]
        ]
    ],
    
    [
        'difficulty' => 'standard_crowd',
        'title' => 'Repeat Customers',
        'description' => 'You recognize this group - they\'ve been through before. They know some of your tricks.',
        'choices' => [
            'A' => [
                'text' => 'Do your usual routine - it works',
                'outcome' => 'They still jump! Some scares are timeless.',
                'guests_scared' => 4,
                'incidents' => 0,
                'momentum' => 1,
                'stat_changes' => ['fear' => 0.02]
            ],
            'B' => [
                'text' => 'Switch it up completely - show them something new',
                'outcome' => 'Excellent! "You changed it!" They\'re delighted by the surprise.',
                'guests_scared' => 6,
                'incidents' => 0,
                'momentum' => 3,
                'stat_changes' => ['fear' => 0.03, 'presence' => 0.03, 'agility' => 0.02]
            ],
            'C' => [
                'text' => 'Acknowledge their return with a character nod, then scare',
                'outcome' => 'Personal touch! They feel recognized and still get scared.',
                'guests_scared' => 5,
                'incidents' => 0,
                'momentum' => 2,
                'stat_changes' => ['presence' => 0.04, 'fear' => 0.02]
            ]
        ]
    ],
    
    // ==================== TOUGH CROWD (Yellow - Medium) ====================
    
    [
        'difficulty' => 'tough_crowd',
        'title' => 'The Commentary Track',
        'description' => 'Someone in the group won\'t stop talking: "Oh that\'s just a person in a costume... I can see the mask seam... Here comes a jump scare..."',
        'choices' => [
            'A' => [
                'text' => 'Target them specifically - shut them up with a perfect scare',
                'outcome' => 'Risky but it worked! They jump and finally stop talking.',
                'guests_scared' => 5,
                'incidents' => 0,
                'momentum' => 1,
                'stat_changes' => ['fear' => 0.03]
            ],
            'B' => [
                'text' => 'Completely change your pattern - do what they don\'t expect',
                'outcome' => 'Perfect! They predicted wrong. Everyone else appreciates it.',
                'guests_scared' => 6,
                'incidents' => 0,
                'momentum' => 3,
                'stat_changes' => ['presence' => 0.04, 'agility' => 0.02]
            ],
            'C' => [
                'text' => 'Use their commentary against them - break the 4th wall briefly',
                'outcome' => 'Clever! You turn it into part of the show. Group laughs.',
                'guests_scared' => 5,
                'incidents' => 0,
                'momentum' => 2,
                'stat_changes' => ['presence' => 0.05, 'voice' => 0.02]
            ],
            'D' => [
                'text' => 'Ignore them, focus on the people actually engaged',
                'outcome' => 'Professional choice. You give the others a great experience.',
                'guests_scared' => 4,
                'incidents' => 0,
                'momentum' => 1,
                'stat_changes' => ['presence' => 0.03]
            ],
            'E' => [
                'text' => 'Make them part of it - rope them into the scene',
                'outcome' => 'Bold! They get flustered, can\'t maintain the critique.',
                'guests_scared' => 5,
                'incidents' => 0,
                'momentum' => 2,
                'stat_changes' => ['presence' => 0.04, 'voice' => 0.03]
            ]
        ]
    ],
    
    [
        'difficulty' => 'tough_crowd',
        'title' => 'Bright Screens Everywhere',
        'description' => 'Three people in the group have their phones out, filming. The bright screens are killing the darkness.',
        'choices' => [
            'A' => [
                'text' => 'Break character and ask them to put phones away',
                'outcome' => 'They comply but the mood is broken. Awkward silence follows.',
                'guests_scared' => 2,
                'incidents' => 0,
                'momentum' => -2,
                'stat_changes' => ['presence' => -0.02]
            ],
            'B' => [
                'text' => 'Perform FOR the camera - give them great content',
                'outcome' => 'Smart! Free marketing. They tag your haunt. Everyone wins.',
                'guests_scared' => 5,
                'incidents' => 0,
                'momentum' => 2,
                'stat_changes' => ['presence' => 0.04, 'fear' => 0.02]
            ],
            'C' => [
                'text' => 'Use the light to your advantage - sudden appearance in their screen',
                'outcome' => 'Brilliant! Their own phones become part of the scare.',
                'guests_scared' => 6,
                'incidents' => 0,
                'momentum' => 3,
                'stat_changes' => ['fear' => 0.04, 'agility' => 0.03]
            ],
            'D' => [
                'text' => 'Work around them, focus on the people not filming',
                'outcome' => 'Professional. The others get a good scare at least.',
                'guests_scared' => 4,
                'incidents' => 0,
                'momentum' => 0,
                'stat_changes' => ['presence' => 0.02]
            ]
        ]
    ],
    
    [
        'difficulty' => 'tough_crowd',
        'title' => 'The Skeptic',
        'description' => 'One guest keeps saying loudly: "This isn\'t even scary. I\'ve seen way better." They\'re bringing down the group\'s energy.',
        'choices' => [
            'A' => [
                'text' => 'Double down - try to prove them wrong',
                'outcome' => 'You get them to flinch but they pretend they didn\'t. Mixed results.',
                'guests_scared' => 3,
                'incidents' => 0,
                'momentum' => 0,
                'stat_changes' => ['fear' => 0.01]
            ],
            'B' => [
                'text' => 'Redirect their energy - make them part of the experience',
                'outcome' => 'Smooth! They can\'t critique when they\'re participating.',
                'guests_scared' => 5,
                'incidents' => 0,
                'momentum' => 2,
                'stat_changes' => ['presence' => 0.05, 'voice' => 0.02]
            ],
            'C' => [
                'text' => 'Subtle psychological scare instead of jump scare',
                'outcome' => 'Perfect read! They expect jump scares. Unease gets them better.',
                'guests_scared' => 6,
                'incidents' => 0,
                'momentum' => 3,
                'stat_changes' => ['presence' => 0.04, 'fear' => 0.03]
            ],
            'D' => [
                'text' => 'Let them talk, focus on making their friends scream',
                'outcome' => 'Smart. When their friends scream, the skeptic looks silly.',
                'guests_scared' => 5,
                'incidents' => 0,
                'momentum' => 1,
                'stat_changes' => ['fear' => 0.02, 'presence' => 0.02]
            ]
        ]
    ],
    
    [
        'difficulty' => 'tough_crowd',
        'title' => 'Overly Confident Group',
        'description' => 'A group of college students walking through like they own the place. "We\'re not scared of anything."',
        'choices' => [
            'A' => [
                'text' => 'Aggressive scare to humble them',
                'outcome' => 'You get one or two but they recover quick. Modest success.',
                'guests_scared' => 4,
                'incidents' => 0,
                'momentum' => 1,
                'stat_changes' => ['fear' => 0.02]
            ],
            'B' => [
                'text' => 'Slow burn - let their bravado work against them',
                'outcome' => 'Excellent! The longer they walk cocky, the harder they fall.',
                'guests_scared' => 7,
                'incidents' => 0,
                'momentum' => 3,
                'stat_changes' => ['presence' => 0.05, 'fear' => 0.03]
            ],
            'C' => [
                'text' => 'Split them up with environmental design',
                'outcome' => 'Perfect! Separate the tough crowd, they\'re vulnerable alone.',
                'guests_scared' => 6,
                'incidents' => 0,
                'momentum' => 2,
                'stat_changes' => ['presence' => 0.03, 'agility' => 0.03]
            ],
            'D' => [
                'text' => 'Let them have their moment, save energy for better guests',
                'outcome' => 'Professional but they leave feeling validated. Missed opportunity.',
                'guests_scared' => 2,
                'incidents' => 0,
                'momentum' => -1,
                'stat_changes' => []
            ]
        ]
    ],
    
    [
        'difficulty' => 'tough_crowd',
        'title' => 'Late Night Fatigue',
        'description' => 'It\'s past midnight. This group looks tired, phones out, just going through the motions. Low energy.',
        'choices' => [
            'A' => [
                'text' => 'Match their energy - save yours too',
                'outcome' => 'Smart for endurance but they barely react. Forgettable.',
                'guests_scared' => 2,
                'incidents' => 0,
                'momentum' => -1,
                'stat_changes' => ['endurance' => 0.02]
            ],
            'B' => [
                'text' => 'Bring high energy to wake them up',
                'outcome' => 'Risky but it works! You revive the group. They needed that.',
                'guests_scared' => 6,
                'incidents' => 0,
                'momentum' => 2,
                'stat_changes' => ['fear' => 0.03, 'presence' => 0.03, 'endurance' => -0.02]
            ],
            'C' => [
                'text' => 'Quick, efficient scare - conserve energy',
                'outcome' => 'Professional! You get the job done without burning out.',
                'guests_scared' => 4,
                'incidents' => 0,
                'momentum' => 1,
                'stat_changes' => ['agility' => 0.03, 'endurance' => 0.03]
            ],
            'D' => [
                'text' => 'Something loud to jolt them awake',
                'outcome' => 'Works! Loud noise snaps them to attention. Classic.',
                'guests_scared' => 5,
                'incidents' => 0,
                'momentum' => 1,
                'stat_changes' => ['fear' => 0.02, 'voice' => 0.02]
            ]
        ]
    ],
    
    // ==================== HOT SITUATION (Orange - Hard) ====================
    
    [
        'difficulty' => 'hot_situation',
        'title' => 'Reaching Out',
        'description' => 'As you scare a guest, they instinctively reach forward and grab your costume. They\'re laughing but they\'re holding fabric.',
        'choices' => [
            'A' => [
                'text' => 'Step back immediately while staying in character',
                'outcome' => 'Perfect! Boundary enforced, scare continues. Professional.',
                'guests_scared' => 4,
                'incidents' => 0,
                'momentum' => 2,
                'stat_changes' => ['presence' => 0.05, 'agility' => 0.03]
            ],
            'B' => [
                'text' => 'Break character, firmly but politely: "Please don\'t touch"',
                'outcome' => 'Safety first. They let go and apologize. Moment is over though.',
                'guests_scared' => 2,
                'incidents' => 0,
                'momentum' => -1,
                'stat_changes' => ['presence' => 0.03, 'voice' => 0.02]
            ],
            'C' => [
                'text' => 'Stay still, wait for them to realize and let go',
                'outcome' => 'They hold a second too long. Uncomfortable for everyone.',
                'guests_scared' => 3,
                'incidents' => 1,
                'momentum' => -2,
                'stat_changes' => ['endurance' => 0.02]
            ],
            'D' => [
                'text' => 'Grab them back to scare them into letting go',
                'outcome' => 'DANGER! You violated protocol. This could escalate badly.',
                'guests_scared' => 3,
                'incidents' => 2,
                'momentum' => -4,
                'stat_changes' => ['fear' => -0.08, 'presence' => -0.05]
            ],
            'E' => [
                'text' => 'Use distraction - loud noise or movement to make them release',
                'outcome' => 'Creative! They let go to react to the distraction. Well done.',
                'guests_scared' => 5,
                'incidents' => 0,
                'momentum' => 1,
                'stat_changes' => ['presence' => 0.04, 'agility' => 0.04]
            ]
        ]
    ],
    
    [
        'difficulty' => 'hot_situation',
        'title' => 'Stumbling Forward',
        'description' => 'A guest is clearly intoxicated. They stumble toward your prop area, hands out for balance.',
        'choices' => [
            'A' => [
                'text' => 'Radio security immediately',
                'outcome' => 'Perfect call! Security escorts them out safely. Crisis avoided.',
                'guests_scared' => 0,
                'incidents' => 0,
                'momentum' => 3,
                'stat_changes' => ['presence' => 0.06, 'voice' => 0.03]
            ],
            'B' => [
                'text' => 'Try to scare them away from the props',
                'outcome' => 'They stumble backward - almost fall! Too risky.',
                'guests_scared' => 1,
                'incidents' => 2,
                'momentum' => -3,
                'stat_changes' => ['fear' => -0.04]
            ],
            'C' => [
                'text' => 'Break character, guide them through safely',
                'outcome' => 'Good safety call. Show paused but no one got hurt.',
                'guests_scared' => 0,
                'incidents' => 0,
                'momentum' => 0,
                'stat_changes' => ['presence' => 0.04, 'endurance' => 0.02]
            ],
            'D' => [
                'text' => 'Stay clear, let them pass quickly',
                'outcome' => 'They knock over a small prop. Minor damage, could\'ve been worse.',
                'guests_scared' => 0,
                'incidents' => 1,
                'momentum' => -1,
                'stat_changes' => ['agility' => 0.02]
            ],
            'E' => [
                'text' => 'Signal other actors to help contain the situation',
                'outcome' => 'Teamwork! Crew helps guide them out. Professional response.',
                'guests_scared' => 0,
                'incidents' => 0,
                'momentum' => 2,
                'stat_changes' => ['presence' => 0.05, 'endurance' => 0.03]
            ]
        ]
    ],
    
    [
        'difficulty' => 'hot_situation',
        'title' => 'Aggressive Posturing',
        'description' => 'One guest is acting tough, getting in your face when you scare. "You wanna go? Come on!" It\'s mostly show but they\'re too close.',
        'choices' => [
            'A' => [
                'text' => 'Hold your ground in character, don\'t engage',
                'outcome' => 'They back down when you don\'t react. Good call.',
                'guests_scared' => 3,
                'incidents' => 0,
                'momentum' => 1,
                'stat_changes' => ['presence' => 0.05, 'endurance' => 0.03]
            ],
            'B' => [
                'text' => 'Break character: "Sir, please step back"',
                'outcome' => 'They comply. Friends tell them to chill. Situation defused.',
                'guests_scared' => 2,
                'incidents' => 0,
                'momentum' => 0,
                'stat_changes' => ['voice' => 0.04, 'presence' => 0.03]
            ],
            'C' => [
                'text' => 'Radio for backup to monitor this guest',
                'outcome' => 'Smart! Security watches them. You can perform without worry.',
                'guests_scared' => 4,
                'incidents' => 0,
                'momentum' => 2,
                'stat_changes' => ['presence' => 0.06]
            ],
            'D' => [
                'text' => 'Ignore them, focus on their friends',
                'outcome' => 'They look silly posturing alone. Friends are embarrassed.',
                'guests_scared' => 4,
                'incidents' => 0,
                'momentum' => 1,
                'stat_changes' => ['presence' => 0.04]
            ],
            'E' => [
                'text' => 'Match their energy to scare them',
                'outcome' => 'RISKY! They take it as a challenge. This could escalate.',
                'guests_scared' => 3,
                'incidents' => 2,
                'momentum' => -3,
                'stat_changes' => ['fear' => -0.05, 'presence' => -0.03]
            ]
        ]
    ],
    
    [
        'difficulty' => 'hot_situation',
        'title' => 'Separated Child',
        'description' => 'A young kid (maybe 8-10) is crying, separated from their group. "I can\'t find my mom!" They\'re genuinely scared now.',
        'choices' => [
            'A' => [
                'text' => 'Break character immediately, comfort them',
                'outcome' => 'Perfect humanity! You calm them down. Radio for parent.',
                'guests_scared' => 0,
                'incidents' => 0,
                'momentum' => 2,
                'stat_changes' => ['presence' => 0.07, 'voice' => 0.04]
            ],
            'B' => [
                'text' => 'Stay in character but gesture them to follow you out',
                'outcome' => 'They\'re too scared to understand. This makes it worse.',
                'guests_scared' => 0,
                'incidents' => 1,
                'momentum' => -2,
                'stat_changes' => ['presence' => -0.03]
            ],
            'C' => [
                'text' => 'Radio for help while staying with them',
                'outcome' => 'Good! Security brings parent. You kept them safe meanwhile.',
                'guests_scared' => 0,
                'incidents' => 0,
                'momentum' => 2,
                'stat_changes' => ['presence' => 0.06, 'endurance' => 0.02]
            ],
            'D' => [
                'text' => 'Walk them back toward entrance while radioing',
                'outcome' => 'Excellent response! Proactive and caring. Parent reunited.',
                'guests_scared' => 0,
                'incidents' => 0,
                'momentum' => 3,
                'stat_changes' => ['presence' => 0.08, 'voice' => 0.03, 'agility' => 0.02]
            ]
        ]
    ],
    
    [
        'difficulty' => 'hot_situation',
        'title' => 'Equipment Malfunction',
        'description' => 'Mid-scare, your fog machine starts pouring out way too much fog. Visibility dropping fast. Guests coughing.',
        'choices' => [
            'A' => [
                'text' => 'Keep performing, it adds to atmosphere',
                'outcome' => 'BAD CALL! Guests can\'t see, someone bumps into wall. Unsafe.',
                'guests_scared' => 2,
                'incidents' => 2,
                'momentum' => -3,
                'stat_changes' => ['fear' => -0.05, 'presence' => -0.04]
            ],
            'B' => [
                'text' => 'Break character, quickly shut off machine',
                'outcome' => 'Smart! Safety first. Fog clears. You resume in 30 seconds.',
                'guests_scared' => 3,
                'incidents' => 0,
                'momentum' => 1,
                'stat_changes' => ['agility' => 0.04, 'presence' => 0.04]
            ],
            'C' => [
                'text' => 'Radio maintenance, guide guests through fog carefully',
                'outcome' => 'Professional! You ensure everyone\'s safe while help comes.',
                'guests_scared' => 2,
                'incidents' => 0,
                'momentum' => 2,
                'stat_changes' => ['presence' => 0.06, 'voice' => 0.03]
            ],
            'D' => [
                'text' => 'Improvise a fog-based scare while signaling for help',
                'outcome' => 'Creative! You turn the malfunction into part of the show.',
                'guests_scared' => 5,
                'incidents' => 0,
                'momentum' => 3,
                'stat_changes' => ['presence' => 0.07, 'agility' => 0.04, 'fear' => 0.03]
            ]
        ]
    ],
    
    // ==================== ALL HANDS (Red - Critical) ====================
    
    [
        'difficulty' => 'all_hands',
        'title' => 'Guest Down',
        'description' => 'A guest suddenly collapses to the ground. Not moving. Others are screaming. You smell alcohol but can\'t tell if it\'s medical.',
        'choices' => [
            'A' => [
                'text' => 'Radio "Code Red" immediately while checking if they\'re responsive',
                'outcome' => 'EXCELLENT! EMTs arrive in 2 minutes. Guest is okay - just passed out drunk. Perfect protocol.',
                'guests_scared' => 0,
                'incidents' => 0,
                'momentum' => 5,
                'stat_changes' => ['presence' => 0.10, 'voice' => 0.05]
            ],
            'B' => [
                'text' => 'Break character, check breathing, call 911',
                'outcome' => 'Good instincts! Emergency services dispatched. You did right.',
                'guests_scared' => 0,
                'incidents' => 0,
                'momentum' => 4,
                'stat_changes' => ['presence' => 0.08, 'voice' => 0.04]
            ],
            'C' => [
                'text' => 'Assume they\'re drunk, try to wake them up',
                'outcome' => 'DANGEROUS! What if it\'s medical? Always call for help first.',
                'guests_scared' => 0,
                'incidents' => 3,
                'momentum' => -5,
                'stat_changes' => ['presence' => -0.08]
            ],
            'D' => [
                'text' => 'Radio for help while directing others to give space',
                'outcome' => 'Perfect crowd control! Help arrives, you kept scene clear.',
                'guests_scared' => 0,
                'incidents' => 0,
                'momentum' => 5,
                'stat_changes' => ['presence' => 0.10, 'voice' => 0.05, 'agility' => 0.03]
            ],
            'E' => [
                'text' => 'Check vitals, radio code, start first aid if needed',
                'outcome' => 'GOLD STANDARD! Full training on display. Lives could depend on this.',
                'guests_scared' => 0,
                'incidents' => 0,
                'momentum' => 6,
                'stat_changes' => ['presence' => 0.12, 'voice' => 0.06, 'endurance' => 0.04]
            ]
        ]
    ],
    
    [
        'difficulty' => 'all_hands',
        'title' => 'Panic Attack',
        'description' => 'A guest is hyperventilating badly. "I can\'t breathe! I need out NOW!" They\'re clawing at their throat, eyes wide.',
        'choices' => [
            'A' => [
                'text' => 'Break character: "Look at me, breathe with me" - guide their breathing',
                'outcome' => 'Excellent! Your calm helps them regulate. Crisis averted.',
                'guests_scared' => 0,
                'incidents' => 0,
                'momentum' => 4,
                'stat_changes' => ['presence' => 0.10, 'voice' => 0.06]
            ],
            'B' => [
                'text' => 'Immediately escort them to exit while radioing for medical',
                'outcome' => 'Good! Fresh air helps. Medical checks them. You did right.',
                'guests_scared' => 0,
                'incidents' => 0,
                'momentum' => 4,
                'stat_changes' => ['agility' => 0.05, 'presence' => 0.08]
            ],
            'C' => [
                'text' => 'Tell them to calm down, it\'s just a haunt',
                'outcome' => 'BAD! This makes panic attacks worse. They spiral further.',
                'guests_scared' => 0,
                'incidents' => 2,
                'momentum' => -4,
                'stat_changes' => ['presence' => -0.06]
            ],
            'D' => [
                'text' => 'Radio for EMT, stay with them, keep others calm',
                'outcome' => 'Perfect! You manage both the guest and the crowd professionally.',
                'guests_scared' => 0,
                'incidents' => 0,
                'momentum' => 5,
                'stat_changes' => ['presence' => 0.11, 'voice' => 0.05, 'endurance' => 0.03]
            ]
        ]
    ],
    
    [
        'difficulty' => 'all_hands',
        'title' => 'Physical Altercation',
        'description' => 'Two guests from different groups are shoving each other. "You pushed my girlfriend!" Voices rising, fists clenched.',
        'choices' => [
            'A' => [
                'text' => 'Radio security CODE RED, stay back, observe',
                'outcome' => 'CORRECT! Security trained for this. You kept yourself safe.',
                'guests_scared' => 0,
                'incidents' => 0,
                'momentum' => 4,
                'stat_changes' => ['presence' => 0.08]
            ],
            'B' => [
                'text' => 'Try to verbally de-escalate while radioing',
                'outcome' => 'Brave but risky. Security arrives fast. Could\'ve gone bad.',
                'guests_scared' => 0,
                'incidents' => 1,
                'momentum' => 2,
                'stat_changes' => ['voice' => 0.05, 'presence' => 0.04]
            ],
            'C' => [
                'text' => 'Step between them to separate',
                'outcome' => 'DANGEROUS! Never put yourself in the middle. You could get hurt.',
                'guests_scared' => 0,
                'incidents' => 3,
                'momentum' => -5,
                'stat_changes' => ['presence' => -0.08, 'endurance' => -0.04]
            ],
            'D' => [
                'text' => 'Radio security, direct other guests away from the area',
                'outcome' => 'Perfect! Protect bystanders. Let security handle combatants.',
                'guests_scared' => 0,
                'incidents' => 0,
                'momentum' => 5,
                'stat_changes' => ['presence' => 0.10, 'voice' => 0.04, 'agility' => 0.03]
            ]
        ]
    ],
    
    [
        'difficulty' => 'all_hands',
        'title' => 'Fire Alarm',
        'description' => 'Fire alarm blaring. You don\'t see smoke but protocol is protocol. 30+ guests in your section.',
        'choices' => [
            'A' => [
                'text' => 'Immediately break character: "Everyone follow me to the nearest exit"',
                'outcome' => 'PERFECT! All guests evacuated in under 2 minutes. Gold standard.',
                'guests_scared' => 0,
                'incidents' => 0,
                'momentum' => 6,
                'stat_changes' => ['presence' => 0.12, 'voice' => 0.08, 'agility' => 0.05]
            ],
            'B' => [
                'text' => 'Check if it\'s real first before evacuating',
                'outcome' => 'WRONG! Never delay evacuation. False alarm this time but never risk it.',
                'guests_scared' => 0,
                'incidents' => 3,
                'momentum' => -6,
                'stat_changes' => ['presence' => -0.10]
            ],
            'C' => [
                'text' => 'Radio for confirmation while starting to guide guests out',
                'outcome' => 'Good balance. You\'re moving people while getting info.',
                'guests_scared' => 0,
                'incidents' => 0,
                'momentum' => 5,
                'stat_changes' => ['presence' => 0.10, 'voice' => 0.06]
            ],
            'D' => [
                'text' => 'Follow emergency protocol exactly as trained',
                'outcome' => 'TEXTBOOK! This is what drills are for. Everyone safe.',
                'guests_scared' => 0,
                'incidents' => 0,
                'momentum' => 6,
                'stat_changes' => ['presence' => 0.12, 'voice' => 0.07, 'endurance' => 0.04]
            ]
        ]
    ],
    
    [
        'difficulty' => 'all_hands',
        'title' => 'Injury Report',
        'description' => 'A guest is bleeding from their head. "I hit it on something!" Blood visible. They\'re dazed, possibly concussed.',
        'choices' => [
            'A' => [
                'text' => 'Radio medical emergency, have them sit, don\'t move them',
                'outcome' => 'EXCELLENT! EMTs arrive. Potential concussion - you did everything right.',
                'guests_scared' => 0,
                'incidents' => 0,
                'momentum' => 6,
                'stat_changes' => ['presence' => 0.12, 'voice' => 0.06]
            ],
            'B' => [
                'text' => 'Walk them to first aid station yourself',
                'outcome' => 'RISKY! Head injuries shouldn\'t be moved. Could make it worse.',
                'guests_scared' => 0,
                'incidents' => 2,
                'momentum' => -2,
                'stat_changes' => ['presence' => 0.04]
            ],
            'C' => [
                'text' => 'Call 911, keep them still, monitor consciousness',
                'outcome' => 'Perfect! You recognize serious head trauma protocol.',
                'guests_scared' => 0,
                'incidents' => 0,
                'momentum' => 6,
                'stat_changes' => ['presence' => 0.12, 'voice' => 0.07, 'endurance' => 0.04]
            ],
            'D' => [
                'text' => 'Radio emergency, control bleeding, keep them talking',
                'outcome' => 'GOLD STANDARD! First aid skills on full display. Potentially saved them.',
                'guests_scared' => 0,
                'incidents' => 0,
                'momentum' => 7,
                'stat_changes' => ['presence' => 0.14, 'voice' => 0.08, 'endurance' => 0.05]
            ]
        ]
    ],
    
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
];
