<?php
/**
 * Default Scenarios for ScareShift Daily Challenge
 * 
 * Each scenario teaches real haunt skills through gameplay
 */

if (!defined('ABSPATH')) exit;

return [
    [
        'title' => 'Fog Machine Failure',
        'description' => 'Your fog machine suddenly dies mid-scene. Guests can now see everything clearly in your haunt zone.',
        'choices' => [
            'A' => [
                'text' => 'Improvise with shadows and sound effects',
                'outcome' => 'You adapt quickly, using the available lighting and audio to maintain atmosphere. Your creativity saves the moment.',
                'stats' => ['presence' => 1, 'fear' => 0],
                'xp_bonus' => 0
            ],
            'B' => [
                'text' => 'Break character briefly to signal tech crew',
                'outcome' => 'You prioritize getting the issue fixed, though it disrupts the immersion temporarily.',
                'stats' => ['presence' => -1, 'endurance' => 1],
                'xp_bonus' => 0
            ],
            'C' => [
                'text' => 'Use the visibility to get closer for intimate scares',
                'outcome' => 'You turn the technical issue into an opportunity, creating memorable up-close encounters.',
                'stats' => ['fear' => 1, 'agility' => 1],
                'xp_bonus' => 5
            ]
        ]
    ],
    
    [
        'title' => 'The Phone Filmer',
        'description' => 'A group of teenagers enters your zone, all filming with their phones, clearly more interested in content than scares.',
        'choices' => [
            'A' => [
                'text' => 'Ignore the cameras and perform for the people behind them',
                'outcome' => 'You stay focused on your craft, successfully scaring guests who actually came for the experience.',
                'stats' => ['presence' => 1, 'fear' => 1],
                'xp_bonus' => 0
            ],
            'B' => [
                'text' => 'Play to the cameras - give them a show worth posting',
                'outcome' => 'You create shareable moments that become free marketing. The teens leave impressed.',
                'stats' => ['presence' => 2, 'fear' => 0],
                'xp_bonus' => 5
            ],
            'C' => [
                'text' => 'Break character and ask them to put phones away',
                'outcome' => 'You stand up for haunt rules, but it kills the mood and creates tension.',
                'stats' => ['presence' => -2, 'voice' => 1],
                'xp_bonus' => 0
            ]
        ]
    ],
    
    [
        'title' => 'Overeager Guest',
        'description' => 'A guest reaches out and grabs your costume, pulling you slightly off balance while their friends laugh.',
        'choices' => [
            'A' => [
                'text' => 'Step back, stay in character, redirect to verbal scare',
                'outcome' => 'You handle it professionally, maintaining safety while keeping the performance going.',
                'stats' => ['presence' => 1, 'agility' => 1],
                'xp_bonus' => 10
            ],
            'B' => [
                'text' => 'Break character and firmly tell them not to touch',
                'outcome' => 'You enforce boundaries clearly. The moment is disrupted but your safety comes first.',
                'stats' => ['voice' => 1, 'presence' => -1],
                'xp_bonus' => 5
            ],
            'C' => [
                'text' => 'Grab them back to scare them more',
                'outcome' => 'DANGER: You violate haunt safety rules. This could result in injury or legal issues.',
                'stats' => ['fear' => -3, 'presence' => -3],
                'xp_bonus' => -20
            ]
        ]
    ],
    
    [
        'title' => 'Voice Fatigue',
        'description' => 'It\'s hour 4 of your shift and your voice is getting hoarse from all the screaming and growling.',
        'choices' => [
            'A' => [
                'text' => 'Switch to whispers and silent scare tactics',
                'outcome' => 'Smart adaptation! You protect your voice while discovering that silence can be terrifying.',
                'stats' => ['voice' => 2, 'fear' => 1],
                'xp_bonus' => 10
            ],
            'B' => [
                'text' => 'Push through - keep using your full voice',
                'outcome' => 'You risk vocal damage. Your performance suffers and you\'ll pay for it tomorrow.',
                'stats' => ['voice' => -2, 'endurance' => -1],
                'xp_bonus' => 0
            ],
            'C' => [
                'text' => 'Take a quick water break when guests clear',
                'outcome' => 'Self-care! You hydrate and rest your voice briefly, returning refreshed.',
                'stats' => ['voice' => 1, 'endurance' => 2],
                'xp_bonus' => 5
            ]
        ]
    ],
    
    [
        'title' => 'Lost Child',
        'description' => 'A young child (maybe 6-7 years old) got separated from their group and is now crying in your scare zone.',
        'choices' => [
            'A' => [
                'text' => 'Break character, comfort them, and radio for help',
                'outcome' => 'Perfect response! Guest safety always comes first, especially children.',
                'stats' => ['presence' => 2, 'voice' => 1],
                'xp_bonus' => 15
            ],
            'B' => [
                'text' => 'Stay in character but be gentler, guide them to exit',
                'outcome' => 'You try to maintain atmosphere while helping, but the child is still scared and confused.',
                'stats' => ['presence' => 0, 'fear' => -1],
                'xp_bonus' => 5
            ],
            'C' => [
                'text' => 'Keep scaring - they chose to come to a haunted house',
                'outcome' => 'TERRIBLE CHOICE: You traumatize a child and create a PR nightmare for the haunt.',
                'stats' => ['fear' => -5, 'presence' => -5],
                'xp_bonus' => -30
            ]
        ]
    ],
    
    [
        'title' => 'Medical Emergency',
        'description' => 'Someone in a guest group suddenly clutches their chest and collapses. Their friends are panicking.',
        'choices' => [
            'A' => [
                'text' => 'Immediately break character, call for medical help, clear the area',
                'outcome' => 'CRITICAL RESPONSE: You handle the emergency perfectly. Lives matter more than scares.',
                'stats' => ['presence' => 3, 'voice' => 2],
                'xp_bonus' => 25
            ],
            'B' => [
                'text' => 'Stay in character but back away to let others handle it',
                'outcome' => 'Delayed response could cost critical seconds. Never hesitate in emergencies.',
                'stats' => ['presence' => -2, 'fear' => -1],
                'xp_bonus' => 0
            ],
            'C' => [
                'text' => 'Assume it\'s part of their group\'s act and keep performing',
                'outcome' => 'DANGEROUS: You dismiss a real emergency. This is unacceptable.',
                'stats' => ['fear' => -4, 'presence' => -4],
                'xp_bonus' => -25
            ]
        ]
    ],
    
    [
        'title' => 'Costume Malfunction',
        'description' => 'Mid-scare, a strap on your elaborate mask breaks, and it\'s slipping off your face.',
        'choices' => [
            'A' => [
                'text' => 'Hold mask with one hand, adjust movements to compensate',
                'outcome' => 'You improvise well, using the partial reveal to create mystery.',
                'stats' => ['agility' => -1, 'presence' => 1],
                'xp_bonus' => 5
            ],
            'B' => [
                'text' => 'Duck into a shadow to fix it quickly',
                'outcome' => 'Smart! You prioritize costume integrity over a single scare moment.',
                'stats' => ['agility' => 1, 'presence' => 1],
                'xp_bonus' => 10
            ],
            'C' => [
                'text' => 'Remove the mask entirely and use your face',
                'outcome' => 'Bold choice! Sometimes breaking the expected is the scariest thing.',
                'stats' => ['fear' => 1, 'presence' => 2],
                'xp_bonus' => 5
            ]
        ]
    ],
    
    [
        'title' => 'Drunk Guests',
        'description' => 'A group of obviously intoxicated adults stumbles into your zone, being loud and disrespectful.',
        'choices' => [
            'A' => [
                'text' => 'Maintain safe distance, don\'t engage physically',
                'outcome' => 'Wise decision. Drunk guests are unpredictable and safety comes first.',
                'stats' => ['presence' => 1, 'agility' => 1],
                'xp_bonus' => 10
            ],
            'B' => [
                'text' => 'Radio security immediately',
                'outcome' => 'Perfect! You alert the team to a potential safety issue before it escalates.',
                'stats' => ['voice' => 1, 'presence' => 2],
                'xp_bonus' => 15
            ],
            'C' => [
                'text' => 'Try to scare them extra hard to shut them up',
                'outcome' => 'Risky! This could provoke aggression from impaired guests.',
                'stats' => ['fear' => -1, 'presence' => -2],
                'xp_bonus' => 0
            ]
        ]
    ],
    
    [
        'title' => 'Perfect Storm',
        'description' => 'Everything aligns: ideal crowd, great lighting, fog at perfect density, and you\'re feeling energized.',
        'choices' => [
            'A' => [
                'text' => 'Go all out - this is your moment to shine!',
                'outcome' => 'LEGENDARY SCARE! You deliver a performance people will remember all season.',
                'stats' => ['fear' => 2, 'presence' => 2, 'voice' => 1],
                'xp_bonus' => 20
            ],
            'B' => [
                'text' => 'Play it safe - stick to your usual routine',
                'outcome' => 'You deliver solid work but miss the opportunity to create magic.',
                'stats' => ['presence' => 1, 'fear' => 0],
                'xp_bonus' => 5
            ],
            'C' => [
                'text' => 'Try something completely new and experimental',
                'outcome' => 'Bold! Your risk either pays off big or teaches you a valuable lesson.',
                'stats' => ['fear' => 1, 'presence' => 1, 'agility' => 1],
                'xp_bonus' => 15
            ]
        ]
    ],
    
    [
        'title' => 'The Heckler',
        'description' => 'One guest in a group keeps loudly announcing your moves: "He\'s gonna jump from there!" "See the fake blood?"',
        'choices' => [
            'A' => [
                'text' => 'Change up your pattern - subvert their expectations',
                'outcome' => 'Clever! You turn their meta-commentary into an advantage by doing the unexpected.',
                'stats' => ['presence' => 2, 'fear' => 1],
                'xp_bonus' => 10
            ],
            'B' => [
                'text' => 'Ignore them completely, focus on scaring the others',
                'outcome' => 'Professional approach. You don\'t let one person ruin it for the group.',
                'stats' => ['presence' => 1, 'endurance' => 1],
                'xp_bonus' => 5
            ],
            'C' => [
                'text' => 'Single them out - make them the target of your scare',
                'outcome' => 'Risky move. Sometimes confronting hecklers directly backfires.',
                'stats' => ['fear' => 0, 'presence' => 0],
                'xp_bonus' => 0
            ]
        ]
    ]
];
