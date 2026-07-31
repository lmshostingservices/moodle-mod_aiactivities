/**
 * AI Learning Activities - Interactive Player v1.4.0
 *
 * 8 activity types: Card Select, Column Sort, Category Sort, Matching, Ordering,
 *                   Flashcards, True/False Swipe, Fill in the Blank
 * Mobile-first with tap mode + desktop drag/drop
 * Unlimited attempts, forced progression, confetti on completion
 *
 * @module     mod_aiactivities/player
 * @copyright  2026 Essay Grader AI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
    'use strict';

    var CONFIG = null;
    var activities = [];
    var currentIndex = 0;
    var attemptId = null;
    var progress = {};
    var hadWrongAnswers = false;
    var advancing = false; // v1.5.54 FIX-DOUBLE-CLICK-SKIP: guard against advanceToNext() firing twice
    var currentVoiceoverSrc = null; // v1.6.8 FIX-AUDIO-BLEED: reference to the playing voiceover BufferSourceNode so it can be stopped before the next activity starts
    var isTouchDevice = false;
    var selectedItem = null;
    var isPreviewMode = false;
    var isReviewMode = false;
    var feedbackTimer = null; // v1.5.43 FIX-WELLDONE-OVERLAP: tracks auto-hide timer so it can be cancelled early
    var reviewIndex = 0;
    var selectedAlaJobLevels = [];  // Multi-select job levels (pill buttons)
    var selectedAlaJobRoles  = [];  // Multi-select job roles (chips input)

    // -- Industry & Sector Data  -  kept in sync with Content Creator --------------
    var INDUSTRIES = [
        'Aged Care', 'Agriculture', 'Automotive', 'Aviation', 'Building & Construction',
        'Business Services', 'Childcare', 'Community Services', 'Education', 'Electrical',
        'Engineering', 'Finance', 'Food Processing', 'Government', 'Healthcare',
        'Hospitality', 'Information Technology', 'Logistics', 'Manufacturing', 'Mining',
        'Plumbing', 'Retail', 'Security', 'Sport & Recreation', 'Tourism', 'Transport',
        'Utilities', 'Warehousing', 'Other'
    ];
    var INDUSTRY_SUBCATEGORIES = {
        'Aged Care': ['Residential Aged Care','Home Care Services','Dementia Care','Palliative Care','Community Aged Care','Retirement Living','Respite Care','Allied Health in Aged Care'],
        'Agriculture': ['Cropping & Grain','Livestock & Cattle','Dairy Farming','Horticulture','Viticulture & Wine','Aquaculture','Poultry','Shearing & Wool','Agricultural Contracting','Irrigation & Water Management'],
        'Automotive': ['Light Vehicle Mechanical','Heavy Vehicle Mechanical','Auto Electrical','Panel Beating & Spray Painting','Motorcycle Technician','Marine Mechanical','Automotive Parts & Accessories','Vehicle Sales','Tyre Fitting'],
        'Aviation': ['Commercial Aviation','General Aviation','Aircraft Maintenance','Ground Operations','Air Traffic Control','Cabin Crew','Aviation Security','Helicopter Operations'],
        'Building & Construction': ['Residential Construction','Commercial Construction','Civil Construction','Mining Construction','Industrial Construction','High-Rise Construction','Renovation & Refurbishment','Demolition','Scaffolding','Formwork','Concreting','Steel Fixing','Carpentry','Bricklaying','Tiling','Painting & Decorating','Plastering','Roofing','Glazing','Waterproofing'],
        'Business Services': ['Accounting & Bookkeeping','Human Resources','Marketing & Advertising','Legal Services','Consulting','Recruitment','Training & Development','Property Management','Cleaning Services','Security Services'],
        'Childcare': ['Long Day Care','Family Day Care','Outside School Hours Care','Kindergarten/Preschool','Occasional Care','In-Home Care','Special Needs Support','Early Intervention'],
        'Community Services': ['Disability Support','Mental Health Support','Youth Work','Family Services','Homelessness Services','Drug & Alcohol Services','Aboriginal & Torres Strait Islander Services','Refugee & Migrant Services','Domestic Violence Support','Case Management'],
        'Education': ['Primary Education','Secondary Education','Vocational Education (VET)','Higher Education/University','TAFE','Adult Education','Special Education','Early Childhood Education','Online/Distance Education','Education Support','Training Administration','School Administration','Private Training Provider (RTO)'],
        'Electrical': ['Domestic Electrical','Commercial Electrical','Industrial Electrical','Instrumentation','Refrigeration & Air Conditioning','Solar Installation','Data & Communications','Fire Protection Systems','Lift Installation'],
        'Engineering': ['Mechanical Engineering','Civil Engineering','Structural Engineering','Electrical Engineering','Chemical Engineering','Mining Engineering','Environmental Engineering','Project Engineering','Maintenance Engineering'],
        'Finance': ['Banking','Insurance','Financial Planning','Mortgage Broking','Credit & Lending','Superannuation','Investment Management','Payroll','Accounts Payable/Receivable','Auditing'],
        'Food Processing': ['Meat Processing','Seafood Processing','Dairy Processing','Bakery','Beverage Manufacturing','Confectionery','Fruit & Vegetable Processing','Ready Meals & Convenience Foods','Quality Assurance','Food Safety'],
        'Government': ['Local Government','State Government','Federal Government','Emergency Services','Regulatory & Compliance','Policy & Planning','Customer Service','Parks & Recreation','Infrastructure','Community Engagement'],
        'Healthcare': ['Acute Care/Hospital','Primary Care/GP','Allied Health','Mental Health','Community Health','Dental','Pharmacy','Pathology','Radiology','Emergency Services','Surgical','Rehabilitation','Infection Control','Aged Care Nursing','Midwifery','Disability Health','Aboriginal Health'],
        'Hospitality': ['Hotels & Accommodation','Restaurants & Cafes','Bars & Pubs','Catering','Events & Functions','Fast Food & Quick Service','Clubs & Gaming','Commercial Cookery','Patisserie','Front Office','Housekeeping'],
        'Information Technology': ['Software Development','Network Administration','Cybersecurity','Cloud Computing','Database Administration','IT Support/Help Desk','Web Development','Data Analytics','Systems Administration','IT Project Management'],
        'Logistics': ['Supply Chain Management','Freight Forwarding','Customs & Border','Inventory Management','Distribution','Third-Party Logistics (3PL)','Last Mile Delivery','Cold Chain Logistics','Dangerous Goods'],
        'Manufacturing': ['Food & Beverage Manufacturing','Pharmaceutical Manufacturing','Chemical Manufacturing','Metal Fabrication','Plastics & Rubber','Textiles','Furniture Manufacturing','Electronics Manufacturing','Printing','Packaging','Process Manufacturing'],
        'Mining': ['Open Cut Mining','Underground Mining','Coal Mining','Iron Ore','Gold Mining','Mineral Processing','Exploration','Drilling','Mine Site Services','Tailings Management','Mine Rehabilitation'],
        'Plumbing': ['Domestic Plumbing','Commercial Plumbing','Industrial Plumbing','Gas Fitting','Roofing & Drainage','Fire Protection Plumbing','Irrigation','Water Treatment','Mechanical Services'],
        'Retail': ['Supermarkets & Grocery','Fashion & Apparel','Electronics & Technology','Hardware & Building','Pharmacy Retail','Furniture & Homewares','Automotive Retail','Sporting Goods','Online/E-commerce','Luxury Retail'],
        'Security': ['Static Security','Mobile Patrol','Event Security','Close Protection','Loss Prevention','Corporate Security','Cash in Transit','CCTV & Monitoring','Access Control','Cybersecurity Operations'],
        'Sport & Recreation': ['Fitness & Personal Training','Aquatics','Outdoor Recreation','Sports Coaching','Sports Administration','Community Recreation','Event Management','Golf & Turf Management','Sports Medicine Support'],
        'Tourism': ['Travel Agencies','Tour Operations','Attractions & Theme Parks','Eco-Tourism','Adventure Tourism','Cultural Tourism','Cruise Operations','Tourism Marketing','Visitor Information Services','Indigenous Tourism'],
        'Transport': ['Road Transport','Rail Transport','Maritime Transport','Air Transport','Public Transport','Taxi & Rideshare','Courier Services','Bus Operations','Heavy Vehicle Operations','Transport Administration'],
        'Utilities': ['Electricity Generation','Electricity Distribution','Gas Distribution','Water Supply','Wastewater Treatment','Renewable Energy','Smart Grid','Meter Reading','Network Maintenance'],
        'Warehousing': ['General Warehousing','Cold Storage','Distribution Centres','Cross-Docking','Hazardous Goods Storage','Automated Warehousing','Order Fulfillment','Returns Processing','Inventory Control'],
        'Other': ['General Industry','Cross-Industry','Emerging Industry']
    };
    function getIndustrySectors(industry) { return INDUSTRY_SUBCATEGORIES[industry] || []; }
    function populateIndustrySelect(el) {
        INDUSTRIES.forEach(function(ind) { var o = document.createElement('option'); o.value = ind; o.textContent = ind; el.appendChild(o); });
    }
    function populateSectorSelect(el, industry) {
        el.innerHTML = '<option value="">Select sector (optional)...</option>';
        getIndustrySectors(industry).forEach(function(s) { var o = document.createElement('option'); o.value = s; o.textContent = s; el.appendChild(o); });
        el.disabled = !industry;
    }
    // ----------------------------------------------------------------------------

    var ICONS = {
        shield: '<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/></svg>',
        book: '<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H19a1 1 0 0 1 1 1v18a1 1 0 0 1-1 1H6.5a1 1 0 0 1 0-5H20"/></svg>',
        lightbulb: '<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 14c.2-1 .7-1.7 1.5-2.5 1-.9 1.5-2.2 1.5-3.5A6 6 0 0 0 6 8c0 1 .2 2.2 1.5 3.5.7.7 1.3 1.5 1.5 2.5"/><path d="M9 18h6"/><path d="M10 22h4"/></svg>',
        gear: '<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg>',
        heart: '<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>',
        star: '<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>',
        check: '<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/></svg>',
        warning: '<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>',
        clock: '<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
        target: '<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg>',
        users: '<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
        tool: '<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>',
        flag: '<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" x2="4" y1="22" y2="15"/></svg>',
        eye: '<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/></svg>',
        lock: '<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>'
    };

    function getIcon(name) {
        return ICONS[name] || ICONS.star;
    }

    // ========== SOUND EFFECTS (Web Audio API) ==========
    var audioCtx = null;
    function getAudioCtx() {
        if (!audioCtx) {
            audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        }
        return audioCtx;
    }

    function playSound(type) {
        try {
            var ctx = getAudioCtx();
            var osc = ctx.createOscillator();
            var gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);
            gain.gain.value = 0.15;

            if (type === 'correct') {
                osc.type = 'sine';
                osc.frequency.setValueAtTime(523.25, ctx.currentTime);
                osc.frequency.setValueAtTime(659.25, ctx.currentTime + 0.1);
                osc.frequency.setValueAtTime(783.99, ctx.currentTime + 0.2);
                gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.4);
                osc.start(ctx.currentTime);
                osc.stop(ctx.currentTime + 0.4);
            } else if (type === 'wrong') {
                osc.type = 'square';
                osc.frequency.setValueAtTime(200, ctx.currentTime);
                osc.frequency.setValueAtTime(150, ctx.currentTime + 0.15);
                gain.gain.value = 0.08;
                gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.3);
                osc.start(ctx.currentTime);
                osc.stop(ctx.currentTime + 0.3);
            } else if (type === 'complete') {
                var notes = [523.25, 659.25, 783.99, 1046.50];
                notes.forEach(function(freq, i) {
                    var o = ctx.createOscillator();
                    var g = ctx.createGain();
                    o.connect(g);
                    g.connect(ctx.destination);
                    o.type = 'sine';
                    o.frequency.value = freq;
                    g.gain.value = 0.12;
                    g.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.15 * i + 0.3);
                    o.start(ctx.currentTime + 0.15 * i);
                    o.stop(ctx.currentTime + 0.15 * i + 0.3);
                });
            } else if (type === 'lock') {
                osc.type = 'sine';
                osc.frequency.setValueAtTime(880, ctx.currentTime);
                gain.gain.value = 0.1;
                gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.15);
                osc.start(ctx.currentTime);
                osc.stop(ctx.currentTime + 0.15);
            } else if (type === 'flip') {
                osc.type = 'sine';
                osc.frequency.setValueAtTime(600, ctx.currentTime);
                osc.frequency.exponentialRampToValueAtTime(900, ctx.currentTime + 0.08);
                gain.gain.value = 0.1;
                gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.12);
                osc.start(ctx.currentTime);
                osc.stop(ctx.currentTime + 0.12);
            } else if (type === 'cardDone') {
                var cdNotes = [659.25, 783.99, 1046.50];
                cdNotes.forEach(function(freq, i) {
                    var o2 = ctx.createOscillator();
                    var g2 = ctx.createGain();
                    o2.connect(g2);
                    g2.connect(ctx.destination);
                    o2.type = 'sine';
                    o2.frequency.value = freq;
                    g2.gain.value = 0.1;
                    g2.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.12 * i + 0.2);
                    o2.start(ctx.currentTime + 0.12 * i);
                    o2.stop(ctx.currentTime + 0.12 * i + 0.2);
                });
            } else if (type === 'swoosh') {
                osc.type = 'sine';
                osc.frequency.setValueAtTime(400, ctx.currentTime);
                osc.frequency.exponentialRampToValueAtTime(800, ctx.currentTime + 0.15);
                gain.gain.value = 0.06;
                gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.18);
                osc.start(ctx.currentTime);
                osc.stop(ctx.currentTime + 0.18);
            }
        } catch(e) {
            // Audio not supported
        }
    }

    // ========== CONFETTI ==========
    function fireConfetti() {
        var canvas = document.createElement('canvas');
        canvas.className = 'ala-confetti-canvas';
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;
        document.body.appendChild(canvas);
        var ctx = canvas.getContext('2d');
        var particles = [];
        var colors = ['#6366f1','#8b5cf6','#ec4899','#f59e0b','#10b981','#3b82f6','#ef4444','#06b6d4'];

        for (var i = 0; i < 150; i++) {
            particles.push({
                x: Math.random() * canvas.width,
                y: Math.random() * canvas.height * 0.5 - canvas.height * 0.5,
                vx: (Math.random() - 0.5) * 8,
                vy: Math.random() * 3 + 2,
                color: colors[Math.floor(Math.random() * colors.length)],
                size: Math.random() * 8 + 4,
                rotation: Math.random() * 360,
                rotationSpeed: (Math.random() - 0.5) * 10,
                opacity: 1
            });
        }

        var startTime = Date.now();
        function animate() {
            var elapsed = Date.now() - startTime;
            if (elapsed > 3000) {
                canvas.remove();
                return;
            }
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            particles.forEach(function(p) {
                p.x += p.vx;
                p.y += p.vy;
                p.vy += 0.1;
                p.rotation += p.rotationSpeed;
                if (elapsed > 2000) {
                    p.opacity = Math.max(0, 1 - (elapsed - 2000) / 1000);
                }
                ctx.save();
                ctx.translate(p.x, p.y);
                ctx.rotate(p.rotation * Math.PI / 180);
                ctx.globalAlpha = p.opacity;
                ctx.fillStyle = p.color;
                ctx.fillRect(-p.size / 2, -p.size / 2, p.size, p.size * 0.6);
                ctx.restore();
            });
            requestAnimationFrame(animate);
        }
        animate();
    }

    // ========== AJAX HELPERS ==========
    function ajaxCall(action, params, callback) {
        params.action = action;
        params.sesskey = CONFIG.sesskey;
        params.cmid = CONFIG.cmid;

        var xhr = new XMLHttpRequest();
        xhr.open('POST', CONFIG.ajaxurl, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                try {
                    var result = JSON.parse(xhr.responseText);
                    callback(result);
                } catch(e) {
                    callback({ok: false, error: 'Parse error'});
                }
            }
        };

        var encoded = [];
        for (var key in params) {
            if (params.hasOwnProperty(key)) {
                encoded.push(encodeURIComponent(key) + '=' + encodeURIComponent(params[key]));
            }
        }
        xhr.send(encoded.join('&'));
    }

    // ========== SHUFFLE ==========
    function shuffle(arr) {
        var a = arr.slice();
        for (var i = a.length - 1; i > 0; i--) {
            var j = Math.floor(Math.random() * (i + 1));
            var temp = a[i];
            a[i] = a[j];
            a[j] = temp;
        }
        return a;
    }

    // ========== UPDATE PROGRESS BAR ==========
    function updateProgressBar() {
        var currentEl = document.getElementById('ala-current-step');
        var totalEl = document.getElementById('ala-total-steps');
        var fillEl = document.getElementById('ala-player-progress-fill');

        if (currentEl) currentEl.textContent = (currentIndex + 1).toString();
        if (totalEl) totalEl.textContent = activities.length.toString();
        if (fillEl) {
            var pct = ((currentIndex + 1) / activities.length) * 100;
            fillEl.style.width = pct + '%';
        }
    }

    // ========== ESTIMATED TIME ==========
    var clockSvg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>';

    function formatEtaTime(totalSeconds) {
        var minutes = Math.ceil(totalSeconds / 60);
        if (minutes < 1) return 'Under 1 minute';
        if (minutes === 1) return '~1 minute';
        if (minutes < 60) return '~' + minutes + ' minutes';
        var hours = Math.floor(minutes / 60);
        var rem = minutes % 60;
        if (rem === 0) return '~' + hours + (hours === 1 ? ' hour' : ' hours');
        return '~' + hours + (hours === 1 ? ' hr ' : ' hrs ') + rem + ' min';
    }

    function calculateActivityEta(acts) {
        var totalSeconds = 0;
        var counts = {};
        acts.forEach(function(act) {
            var t = act.type || 'unknown';
            counts[t] = (counts[t] || 0) + 1;
            switch(t) {
                case 'truefalseswipe':
                    totalSeconds += (act.statements ? act.statements.length : 5) * 45;
                    break;
                case 'cardselect':
                    totalSeconds += (act.cards ? act.cards.length : 4) * 30;
                    break;
                case 'flashcards':
                    totalSeconds += (act.cards ? act.cards.length : 4) * 40;
                    break;
                case 'matching':
                    totalSeconds += (act.pairs ? act.pairs.length : 4) * 40;
                    break;
                case 'ordering':
                    totalSeconds += (act.items ? act.items.length : 5) * 35;
                    break;
                case 'columnsort':
                    totalSeconds += (act.items ? act.items.length : 6) * 35;
                    break;
                case 'categorysort':
                    var catItems = 0;
                    if (act.items) catItems = act.items.length;
                    totalSeconds += Math.max(catItems, 4) * 35;
                    break;
                case 'fillinblank':
                    totalSeconds += (act.items ? act.items.length : 3) * 40;
                    break;
                default:
                    totalSeconds += 90;
            }
        });
        return { seconds: totalSeconds, counts: counts };
    }

    function buildEtaBannerHtml(acts) {
        var eta = calculateActivityEta(acts);
        var timeStr = formatEtaTime(eta.seconds);
        var parts = [];
        for (var key in eta.counts) {
            var label = key.replace('cardselect', 'Card Select')
                .replace('columnsort', 'Column Sort')
                .replace('categorysort', 'Category Sort')
                .replace('matching', 'Matching')
                .replace('ordering', 'Ordering')
                .replace('flashcards', 'Flashcards')
                .replace('truefalseswipe', 'True/False')
                .replace('fillinblank', 'Fill in Blank');
            parts.push(eta.counts[key] + ' ' + label);
        }
        var detail = acts.length + ' activities' + (parts.length > 0 ? ' (' + parts.join(', ') + ')' : '');
        return '<div class="ala-eta-banner">' +
            '<div class="ala-eta-icon-wrap">' + clockSvg + '</div>' +
            '<div class="ala-eta-body">' +
            '<span class="ala-eta-label">Estimated completion time</span>' +
            '<span class="ala-eta-time">' + timeStr + '</span>' +
            '<span class="ala-eta-detail">' + detail + '</span>' +
            '</div></div>';
    }

    // ========== SHOW FEEDBACK ==========
    // Helper: immediately hide a feedback element (clears any inline style override).
    function hideFeedbackEl(el) {
        if (!el) return;
        el.style.removeProperty('display');
        el.classList.add('ala-feedback-hidden');
    }

    function showFeedback(correct, message) {
        var feedbackId = isPreviewMode ? 'ala-preview-feedback' : 'ala-player-feedback';
        var feedbackEl = document.getElementById(feedbackId);
        if (!feedbackEl) return;

        // BUG-FEEDBACK-PERSIST (v1.5.46): Use classList.remove/add instead of overwriting
        // className. Overwriting className dropped the ala-feedback-hidden class in some
        // edge cases and also lost the base ala-player-feedback class in others, meaning the
        // CSS :not(.ala-feedback-hidden) show rule could misfire. Using classList ensures the
        // base class is always present and hidden/shown state is toggled cleanly.
        feedbackEl.style.removeProperty('display');  // clear any lingering inline style
        feedbackEl.classList.remove('ala-feedback-correct', 'ala-feedback-incorrect');
        feedbackEl.classList.add(correct ? 'ala-feedback-correct' : 'ala-feedback-incorrect');
        // Reveal: remove the hidden class  -  CSS :not(.ala-feedback-hidden) then shows it.
        feedbackEl.classList.remove('ala-feedback-hidden');

        feedbackEl.innerHTML = '<div class="ala-feedback-content">' +
            '<span class="ala-feedback-icon">' + (correct ? '&#10004;' : '&#10008;') + '</span>' +
            '<span class="ala-feedback-text">' + message + '</span>' +
            '</div>';

        playSound(correct ? 'correct' : 'wrong');

        // Cancel any previous auto-hide before starting a new one.
        if (feedbackTimer !== null) {
            clearTimeout(feedbackTimer);
        }
        feedbackTimer = setTimeout(function() {
            hideFeedbackEl(feedbackEl);
            feedbackTimer = null;
        }, 1500);
    }

    // ========== SAVE PROGRESS ==========
    function saveProgress() {
        if (isPreviewMode || !attemptId) return;
        var completedCount = 0;
        for (var key in progress) {
            if (progress[key] === true) completedCount++;
        }
        ajaxCall('saveprogress', {
            attemptid: attemptId,
            currentactivity: currentIndex,
            progressjson: JSON.stringify(progress),
            completedcount: completedCount
        }, function() {});
    }

    // ========== ADVANCE TO NEXT ==========
    function advanceToNext() {
        // v1.5.54 FIX-DOUBLE-CLICK-SKIP: If the student clicks Done twice in quick
        // succession (within the 800ms renderActivity delay), the second call would
        // increment currentIndex a second time  -  marking the as-yet-unseen next activity
        // as complete and skipping it entirely. The advancing flag gates the function so
        // only one advancement can be in flight at a time. It is cleared just before
        // renderActivity() fires and also reset by practiceAgain() / review mode.
        if (advancing) return;
        advancing = true;

        if (isPreviewMode) {
            advancing = false;
            showFeedback(true, 'Activity complete! Returning to list...');
            setTimeout(function() {
                closeTeacherPreview();
            }, 1500);
            return;
        }
        if (isReviewMode) {
            advancing = false;
            showFeedback(true, CONFIG.strings.feedback_welldone || 'Well done!');
            return;
        }
        // v1.6.8 FIX-FEEDBACK-GAP: Do NOT hide the feedback toast here. renderActivity()
        // already clears it as its very first action, so hiding it early just creates an
        // ugly blank gap (no feedback AND no activity) for ~800ms. Let the toast remain
        // visible through the delay — it disappears naturally when the next activity renders.
        currentIndex++;
        if (currentIndex >= activities.length) {
            saveProgress();
            advancing = false;
            completeAllActivities();
        } else {
            saveProgress();
            setTimeout(function() {
                advancing = false;
                renderActivity(currentIndex);
            }, 800);
        }
    }

    // ========== MOODLE NAVIGATION LOCK ==========
    var ALA_NAV_SELECTOR = [
        '[data-action="next-activity-link"]',
        '[data-action="next"]',
        '.activity-navigation .nav-link[data-action]',
        '.activity-navigation a',
        '.course-content-nextprev .next',
        '.nextprevpage .next',
        '.activity_navigation .nav-next a',
        '.prevnext .next a'
    ].join(', ');

    function lockMoodleNav() {
        var els = document.querySelectorAll(ALA_NAV_SELECTOR);
        els.forEach(function(el) {
            if (el.getAttribute('data-ala-nav-locked')) return;
            el.setAttribute('data-ala-nav-locked', '1');
            el.setAttribute('data-ala-nav-href', el.getAttribute('href') || '');
            el.style.pointerEvents = 'none';
            el.style.opacity = '0.4';
            el.setAttribute('title', 'Complete all activities to continue');
            el.setAttribute('href', 'javascript:void(0)');
        });
    }

    function unlockMoodleNav() {
        // v1.5.13: Also sweep by selector so any elements added to the DOM after
        // lockMoodleNav() ran (e.g. dynamically rendered nav) also get unlocked.
        var allNavEls = document.querySelectorAll(ALA_NAV_SELECTOR);
        allNavEls.forEach(function(el) {
            el.style.removeProperty('pointer-events');
            el.style.removeProperty('opacity');
        });

        var els = document.querySelectorAll('[data-ala-nav-locked="1"]');
        els.forEach(function(el) {
            el.removeAttribute('data-ala-nav-locked');
            el.style.removeProperty('pointer-events');
            el.style.removeProperty('opacity');
            el.removeAttribute('title');
            var origHref = el.getAttribute('data-ala-nav-href');
            // v1.5.13: use != null check  -  an empty-string href is still a valid
            // restore value; the old `if (origHref)` check was falsy for ''.
            if (origHref != null) {
                el.setAttribute('href', origHref);
            }
            el.removeAttribute('data-ala-nav-href');
        });
    }

    // ========== COMPLETE ALL ==========
    function showCompleteScreen() {
        unlockMoodleNav();

        // BUG-FEEDBACK-PERSIST (v1.5.46): ala-player-feedback is a SIBLING of
        // ala-player-content, so hiding ala-player-content does NOT hide the toast.
        // Clear the timer and dismiss the feedback immediately before showing the
        // complete screen, otherwise the toast overlaps the congratulations panel.
        if (feedbackTimer !== null) {
            clearTimeout(feedbackTimer);
            feedbackTimer = null;
        }
        var completeFbEl = document.getElementById(isPreviewMode ? 'ala-preview-feedback' : 'ala-player-feedback');
        hideFeedbackEl(completeFbEl);

        var playerContent = document.getElementById('ala-player-content');
        var completeScreen = document.getElementById('ala-player-complete');
        if (playerContent) playerContent.style.display = 'none';
        if (completeScreen) completeScreen.style.display = 'flex';

        var fillEl = document.getElementById('ala-player-progress-fill');
        if (fillEl) fillEl.style.width = '100%';

        var continueBtn = document.getElementById('ala-continue-btn');
        if (continueBtn && ALA_CONFIG.nextactivityurl) {
            continueBtn.href = ALA_CONFIG.nextactivityurl;
            continueBtn.style.display = 'inline-flex';
        }

        playSound('complete');
        if (!hadWrongAnswers) {
            setTimeout(fireConfetti, 300);
        }
    }

    function completeAllActivities() {
        if (!attemptId) return;
        ajaxCall('complete', { attemptid: attemptId }, function(result) {
            showCompleteScreen();
        });
    }

    // ========== PRACTICE AGAIN ==========
    function practiceAgain() {
        currentIndex = 0;
        progress = {};
        attemptId = null;
        hadWrongAnswers = false;
        advancing = false; // v1.5.54: always reset so the fresh attempt isn't blocked

        // Immediately clear the feedback toast so old messages (e.g. "Activity Done") are
        // not visible during the startattempt AJAX gap. renderActivity(0) hides it too,
        // but only after the round-trip  -  this guarantees an instant hide on button click.
        if (feedbackTimer !== null) { clearTimeout(feedbackTimer); feedbackTimer = null; }
        hideFeedbackEl(document.getElementById('ala-player-feedback'));

        var completeScreen = document.getElementById('ala-player-complete');
        var playerContent = document.getElementById('ala-player-content');
        var headerEl = document.getElementById('ala-player-header');
        var reviewNav = document.getElementById('ala-review-nav');

        if (completeScreen) completeScreen.style.display = 'none';
        if (playerContent) playerContent.style.display = 'block';
        if (headerEl) headerEl.style.display = 'block';
        if (reviewNav) reviewNav.style.display = 'none';

        var fillEl = document.getElementById('ala-player-progress-fill');
        if (fillEl) fillEl.style.width = '0%';

        ajaxCall('startattempt', {}, function(result) {
            if (!result.ok) return;
            attemptId = result.attemptid;
            renderActivity(0);
        });
    }

    // ========== REVIEW MODE ==========
    function enterReviewMode() {
        isReviewMode = true;
        reviewIndex = 0;

        var completeScreen = document.getElementById('ala-player-complete');
        var playerContent = document.getElementById('ala-player-content');
        var reviewNav = document.getElementById('ala-review-nav');
        var headerEl = document.getElementById('ala-player-header');

        if (completeScreen) completeScreen.style.display = 'none';
        if (playerContent) playerContent.style.display = 'block';
        if (reviewNav) reviewNav.style.display = 'flex';
        if (headerEl) headerEl.style.display = 'none';

        renderReviewActivity(reviewIndex);
        updateReviewNav();
    }

    function exitReviewMode() {
        isReviewMode = false;

        var completeScreen = document.getElementById('ala-player-complete');
        var playerContent = document.getElementById('ala-player-content');
        var reviewNav = document.getElementById('ala-review-nav');
        var headerEl = document.getElementById('ala-player-header');

        if (playerContent) playerContent.style.display = 'none';
        if (reviewNav) reviewNav.style.display = 'none';
        if (completeScreen) completeScreen.style.display = 'flex';
        if (headerEl) {
            headerEl.style.display = '';
            var fillEl = document.getElementById('ala-player-progress-fill');
            if (fillEl) fillEl.style.width = '100%';
        }
    }

    function renderReviewActivity(index) {
        var container = document.getElementById('ala-player-content');
        if (!container || !activities[index]) return;

        container.style.display = 'block';
        container.classList.remove('ala-animate-slide-in');
        void container.offsetWidth;
        container.classList.add('ala-animate-slide-in');

        var activity = activities[index];
        switch (activity.type) {
            case 'cardselect': renderReviewCardSelect(activity, container); break;
            case 'columnsort': renderReviewColumnSort(activity, container); break;
            case 'categorysort': renderReviewCategorySort(activity, container); break;
            case 'matching': renderReviewMatching(activity, container); break;
            case 'ordering': renderReviewOrdering(activity, container); break;
            case 'flashcards': renderReviewFlashcards(activity, container); break;
            case 'truefalseswipe': renderReviewTrueFalseSwipe(activity, container); break;
            case 'fillinblank': renderReviewFillInBlank(activity, container); break;
            default: container.innerHTML = '<p>Unknown activity type</p>';
        }
    }

    function renderReviewCardSelect(activity, container) {
        var html = '<div class="ala-activity ala-cardselect ala-review-mode">';
        html += '<h3 class="ala-activity-title">' + escapeHtml(activity.title) + '</h3>';
        html += '<p class="ala-activity-question">' + escapeHtml(activity.question) + '</p>';
        html += '<div class="ala-cards-grid">';
        activity.cards.forEach(function(card, idx) {
            var isCorrect = idx === activity.correctIndex;
            html += '<div class="ala-card-option' + (isCorrect ? ' ala-correct' : '') + '" style="pointer-events:none;">';
            html += '<div class="ala-card-icon">' + getIcon(card.icon || 'star') + '</div>';
            html += '<div class="ala-card-label">' + escapeHtml(card.label) + '</div>';
            html += '<div class="ala-card-desc">' + escapeHtml(card.description) + '</div>';
            html += '</div>';
        });
        html += '</div></div>';
        container.innerHTML = html;
    }

    function renderReviewColumnSort(activity, container) {
        var html = '<div class="ala-activity ala-columnsort ala-review-mode">';
        html += '<h3 class="ala-activity-title">' + escapeHtml(activity.title) + '</h3>';
        if (activity.question) html += '<p class="ala-activity-question">' + escapeHtml(activity.question) + '</p>';
        html += '<div class="ala-columns-layout">';
        html += '<div class="ala-column ala-column-a"><div class="ala-column-header ala-column-header-a"><svg class="ala-col-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>' + escapeHtml(activity.columnA) + '</div><div class="ala-column-items">';
        activity.items.forEach(function(item) {
            if (item.column === 'A') {
                html += '<div class="ala-locked-item ala-correct">' + escapeHtml(item.text) + '</div>';
            }
        });
        html += '</div></div>';
        html += '<div class="ala-column ala-column-b"><div class="ala-column-header ala-column-header-b"><svg class="ala-col-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>' + escapeHtml(activity.columnB) + '</div><div class="ala-column-items">';
        activity.items.forEach(function(item) {
            if (item.column === 'B') {
                html += '<div class="ala-locked-item ala-correct">' + escapeHtml(item.text) + '</div>';
            }
        });
        html += '</div></div>';
        html += '</div></div>';
        container.innerHTML = html;
    }

    function renderReviewCategorySort(activity, container) {
        var html = '<div class="ala-activity ala-categorysort ala-review-mode">';
        html += '<h3 class="ala-activity-title">' + escapeHtml(activity.title) + '</h3>';
        if (activity.question) html += '<p class="ala-activity-question">' + escapeHtml(activity.question) + '</p>';
        html += '<div class="ala-categories-grid">';
        activity.categories.forEach(function(catName, catIdx) {
            html += '<div class="ala-category-bucket">';
            html += '<div class="ala-category-header">' + escapeHtml(catName) + '</div>';
            html += '<div class="ala-category-items">';
            activity.items.forEach(function(item) {
                // item.category may be a numeric index or a string name  -  accept both.
                if (item.category === catName || item.category === catIdx) {
                    html += '<div class="ala-locked-item ala-correct">' + escapeHtml(item.text) + '</div>';
                }
            });
            html += '</div></div>';
        });
        html += '</div></div>';
        container.innerHTML = html;
    }

    function renderReviewMatching(activity, container) {
        var html = '<div class="ala-activity ala-matching ala-review-mode">';
        html += '<h3 class="ala-activity-title">' + escapeHtml(activity.title) + '</h3>';
        if (activity.question) html += '<p class="ala-activity-question">' + escapeHtml(activity.question) + '</p>';
        html += '<div class="ala-matching-layout">';
        html += '<div class="ala-matching-col ala-matching-left">';
        activity.pairs.forEach(function(pair) {
            html += '<div class="ala-match-item ala-match-left ala-matched">' + escapeHtml(pair.left) + '</div>';
        });
        html += '</div>';
        html += '<div class="ala-matching-col ala-matching-right">';
        activity.pairs.forEach(function(pair) {
            html += '<div class="ala-match-item ala-match-right ala-matched">' + escapeHtml(pair.right) + '</div>';
        });
        html += '</div>';
        html += '</div></div>';
        container.innerHTML = html;
    }

    function renderReviewOrdering(activity, container) {
        var html = '<div class="ala-activity ala-ordering ala-review-mode">';
        html += '<h3 class="ala-activity-title">' + escapeHtml(activity.title) + '</h3>';
        if (activity.question) html += '<p class="ala-activity-question">' + escapeHtml(activity.question) + '</p>';
        html += '<div class="ala-ordering-list">';
        activity.items.forEach(function(item, idx) {
            html += '<div class="ala-order-item ala-correct" style="pointer-events:none;">';
            html += '<span class="ala-order-num">' + (idx + 1) + '</span>';
            html += '<span class="ala-order-text">' + escapeHtml(item) + '</span>';
            html += '</div>';
        });
        html += '</div></div>';
        container.innerHTML = html;
    }

    function renderReviewFlashcards(activity, container) {
        var html = '<div class="ala-activity ala-flashcards ala-review-mode">';
        html += '<h3 class="ala-activity-title">' + escapeHtml(activity.title) + '</h3>';
        if (activity.question) html += '<p class="ala-activity-question">' + escapeHtml(activity.question) + '</p>';
        html += '<div class="ala-review-flashcards-list">';
        activity.cards.forEach(function(card, idx) {
            html += '<div class="ala-review-flashcard-item">';
            html += '<div class="ala-review-fc-front"><strong>' + (idx + 1) + '.</strong> ' + escapeHtml(card.front) + '</div>';
            html += '<div class="ala-review-fc-back">' + escapeHtml(card.back) + '</div>';
            html += '</div>';
        });
        html += '</div></div>';
        container.innerHTML = html;
    }

    function renderReviewTrueFalseSwipe(activity, container) {
        var html = '<div class="ala-activity ala-truefalseswipe ala-review-mode">';
        html += '<h3 class="ala-activity-title">' + escapeHtml(activity.title) + '</h3>';
        if (activity.question) html += '<p class="ala-activity-question">' + escapeHtml(activity.question) + '</p>';
        html += '<div class="ala-review-tfs-list">';
        activity.statements.forEach(function(stmt, idx) {
            html += '<div class="ala-review-tfs-item">';
            html += '<div class="ala-review-tfs-statement">';
            html += '<span class="ala-review-tfs-num">' + (idx + 1) + '</span>';
            html += '<span>' + escapeHtml(stmt.text) + '</span>';
            html += '<span class="' + (stmt.correct ? 'ala-tfs-true-badge' : 'ala-tfs-false-badge') + '">' +
                (stmt.correct ? 'True' : 'False') + '</span>';
            html += '</div>';
            html += '<div class="ala-review-tfs-explanation">' + escapeHtml(stmt.explanation) + '</div>';
            html += '</div>';
        });
        html += '</div></div>';
        container.innerHTML = html;
    }

    function renderReviewFillInBlank(activity, container) {
        var html = '<div class="ala-activity ala-fillinblank ala-review-mode">';
        html += '<h3 class="ala-activity-title">' + escapeHtml(activity.title) + '</h3>';
        if (activity.question) html += '<p class="ala-activity-question">' + escapeHtml(activity.question) + '</p>';
        html += '<div class="ala-fib-text">';
        var text = activity.text;
        for (var i = activity.blanks.length; i >= 1; i--) {
            var placeholder = '___' + i + '___';
            var answer = activity.blanks[i - 1].answer;
            text = text.replace(placeholder, '<span class="ala-fib-blank ala-fib-filled ala-correct">' + escapeHtml(answer) + '</span>');
        }
        html += '<p class="ala-fib-passage">' + text + '</p>';
        html += '</div></div>';
        container.innerHTML = html;
    }

    function updateReviewNav() {
        var prevBtn = document.getElementById('ala-review-prev');
        var nextBtn = document.getElementById('ala-review-next');
        var indicator = document.getElementById('ala-review-indicator');

        if (prevBtn) prevBtn.disabled = (reviewIndex <= 0);
        if (nextBtn) nextBtn.disabled = (reviewIndex >= activities.length - 1);
        if (indicator) {
            var activity = activities[reviewIndex];
            var typeLabels = {
                cardselect: 'Card Select',
                columnsort: 'Column Sort',
                categorysort: 'Category Sort',
                matching: 'Matching Pairs',
                ordering: 'Ordering',
                flashcards: 'Flashcards',
                truefalseswipe: 'True or False',
                fillinblank: 'Fill in the Blank'
            };
            var label = typeLabels[activity.type] || activity.type;
            indicator.textContent = (reviewIndex + 1) + ' / ' + activities.length + '   -   ' + label;
        }
    }

    function reviewPrev() {
        if (reviewIndex > 0) {
            reviewIndex--;
            renderReviewActivity(reviewIndex);
            updateReviewNav();
        }
    }

    function reviewNext() {
        if (reviewIndex < activities.length - 1) {
            reviewIndex++;
            renderReviewActivity(reviewIndex);
            updateReviewNav();
        }
    }

    // ===================================================================
    // ACTIVITY RENDERER: CARD SELECT
    // ===================================================================
    function renderCardSelect(activity, container) {
        var html = '<div class="ala-activity ala-cardselect">';
        html += '<h3 class="ala-activity-title">' + escapeHtml(activity.title) + '</h3>';
        html += '<p class="ala-activity-question">' + escapeHtml(activity.question) + '</p>';
        html += '<p class="ala-activity-instruction">' + CONFIG.strings.cardselect_instructions + '</p>';
        // v1.5.35 BUG-TF-INLINE-RESULT: Inline result strip replaces floating showFeedback()
        // overlay. Testers reported OK Correct / x Incorrect appearing "outside the box /
        // between the option boxes" because showFeedback() used a separate fixed-position
        // element (ala-player-feedback) rendered entirely outside the cards grid.
        // Fix: add a hidden inline-result div inside the activity area (between instruction
        // and the cards grid); on click, show it with correct/incorrect status + the correct
        // card label ("Answer: True" / "Answer: False" etc). Reuses ala-tfs-mid-result CSS.
        html += '<div class="ala-cardselect-inline-result" id="ala-cardselect-inline-result"></div>';
        html += '<div class="ala-cards-grid">';

        activity.cards.forEach(function(card, idx) {
            html += '<div class="ala-card-option" data-index="' + idx + '">';
            html += '<div class="ala-card-icon">' + getIcon(card.icon || 'star') + '</div>';
            html += '<div class="ala-card-label">' + escapeHtml(card.label) + '</div>';
            html += '<div class="ala-card-desc">' + escapeHtml(card.description) + '</div>';
            html += '</div>';
        });

        html += '</div></div>';
        container.innerHTML = html;

        var resultEl = container.querySelector('#ala-cardselect-inline-result');
        var tickSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>';
        var crossSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>';

        var cards = container.querySelectorAll('.ala-card-option');
        cards.forEach(function(card) {
            card.addEventListener('click', function() {
                var idx = parseInt(this.getAttribute('data-index'));
                var correct = idx === activity.correctIndex;

                cards.forEach(function(c) {
                    c.classList.remove('ala-selected', 'ala-correct', 'ala-incorrect');
                    c.style.pointerEvents = 'none';
                });

                var correctCardObj = activity.cards[activity.correctIndex] || {};
                var correctLabel = correctCardObj.label ? escapeHtml(correctCardObj.label) : '';

                if (correct) {
                    this.classList.add('ala-correct');
                    if (resultEl) {
                        resultEl.className = 'ala-cardselect-inline-result ala-tfs-mid-result ala-tfs-result-correct';
                        resultEl.innerHTML = tickSvg + ' ' + (CONFIG.strings.feedback_correct || 'Correct!');
                    }
                    playSound('correct');
                    progress[currentIndex] = true;
                    setTimeout(advanceToNext, 800);
                } else {
                    this.classList.add('ala-incorrect');
                    var correctCard = container.querySelector('[data-index="' + activity.correctIndex + '"]');
                    if (correctCard) correctCard.classList.add('ala-correct');
                    if (resultEl) {
                        resultEl.className = 'ala-cardselect-inline-result ala-tfs-mid-result ala-tfs-result-incorrect';
                        var answerLine = correctLabel
                            ? '<span style="display:block;font-size:12px;font-weight:500;margin-top:4px;opacity:0.85;">' + (CONFIG.strings.truefalse_answer || 'Answer') + ': ' + correctLabel + '</span>'
                            : '';
                        resultEl.innerHTML = '<span style="display:flex;align-items:center;gap:5px;justify-content:center;">' + crossSvg + ' ' + (CONFIG.strings.feedback_incorrect || 'Incorrect!') + '</span>' + answerLine;
                    }
                    playSound('wrong');

                    setTimeout(function() {
                        cards.forEach(function(c) {
                            c.classList.remove('ala-selected', 'ala-correct', 'ala-incorrect');
                            c.style.pointerEvents = '';
                        });
                        if (resultEl) {
                            resultEl.className = 'ala-cardselect-inline-result';
                            resultEl.innerHTML = '';
                        }
                    }, 2000);
                }
            });
        });
    }

    // ===================================================================
    // ACTIVITY RENDERER: COLUMN SORT (2 columns, items one at a time)
    // ===================================================================
    function renderColumnSort(activity, container) {
        var items = shuffle(activity.items.slice());
        var currentItemIdx = 0;
        var correctCount = 0;
        var totalItems = items.length;

        function renderState() {
            var html = '<div class="ala-activity ala-columnsort">';
            html += '<h3 class="ala-activity-title">' + escapeHtml(activity.title) + '</h3>';
            if (activity.question) html += '<p class="ala-activity-question">' + escapeHtml(activity.question) + '</p>';
            html += '<p class="ala-activity-instruction">' + CONFIG.strings.columnsort_instructions + '</p>';

            html += '<div class="ala-columnsort-progress">';
            html += '<div class="ala-columnsort-progress-bar"><div class="ala-columnsort-progress-fill" style="width:' +
                (correctCount / totalItems * 100) + '%"></div></div>';
            html += '<span>' + correctCount + ' / ' + totalItems + '</span>';
            html += '</div>';

            html += '<div class="ala-columns-layout">';
            html += '<div class="ala-column ala-column-a" data-col="A"><div class="ala-column-header ala-column-header-a"><svg class="ala-col-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>' + escapeHtml(activity.columnA) + '</div><div class="ala-column-items" id="ala-col-a"></div></div>';
            html += '<div class="ala-column ala-column-b" data-col="B"><div class="ala-column-header ala-column-header-b"><svg class="ala-col-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>' + escapeHtml(activity.columnB) + '</div><div class="ala-column-items" id="ala-col-b"></div></div>';
            html += '</div>';

            if (currentItemIdx < totalItems) {
                var item = items[currentItemIdx];
                html += '<div class="ala-current-item-container">';
                html += '<div class="ala-current-item ala-animate-slide-in" id="ala-sort-item">' + escapeHtml(item.text) + '</div>';
                html += '</div>';
            }

            html += '</div>';
            container.innerHTML = html;

            // Re-render locked items
            var colAEl = document.getElementById('ala-col-a');
            var colBEl = document.getElementById('ala-col-b');
            for (var i = 0; i < currentItemIdx; i++) {
                var lockedItem = document.createElement('div');
                lockedItem.className = 'ala-locked-item';
                lockedItem.textContent = items[i].text;
                if (items[i].column === 'A' && colAEl) colAEl.appendChild(lockedItem);
                if (items[i].column === 'B' && colBEl) colBEl.appendChild(lockedItem);
            }

            function handleColumnChoice(chosenCol) {
                var item = items[currentItemIdx];
                var correct = item.column === chosenCol;

                if (correct) {
                    playSound('lock');
                    correctCount++;
                    currentItemIdx++;
                    renderState();
                    if (currentItemIdx >= totalItems) {
                        showFeedback(true, CONFIG.strings.feedback_welldone);
                        progress[currentIndex] = true;
                        setTimeout(advanceToNext, 900);
                    }
                } else {
                    var sortItem = document.getElementById('ala-sort-item');
                    if (sortItem) {
                        sortItem.classList.add('ala-shake');
                        setTimeout(function() { sortItem.classList.remove('ala-shake'); }, 500);
                    }
                    showFeedback(false, CONFIG.strings.feedback_incorrect);
                }
            }

            // Bind columns as clickable drop targets (tap or click)
            var columns = container.querySelectorAll('.ala-column');
            columns.forEach(function(col) {
                if (currentItemIdx < totalItems) {
                    col.classList.add('ala-column-clickable');
                    col.addEventListener('click', function() {
                        handleColumnChoice(col.getAttribute('data-col'));
                    });
                }

                // Drag and drop for desktop
                col.addEventListener('dragover', function(e) {
                    e.preventDefault();
                    col.classList.add('ala-drop-target');
                });
                col.addEventListener('dragleave', function() {
                    col.classList.remove('ala-drop-target');
                });
                col.addEventListener('drop', function(e) {
                    e.preventDefault();
                    col.classList.remove('ala-drop-target');
                    handleColumnChoice(col.getAttribute('data-col'));
                });
            });

            // FIX-DRAG-TOUCH: always enable draggable regardless of isTouchDevice.
            // On touch-primary devices the dragstart event simply won't fire, so
            // enabling draggable is harmless. On hybrid (touch + mouse) laptops the
            // old !isTouchDevice guard caused drag to be silently disabled even
            // though the user was operating with a mouse.  Clicking a column still
            // works on pure touch devices via the click handler above.
            var sortItem = document.getElementById('ala-sort-item');
            if (sortItem) {
                sortItem.setAttribute('draggable', 'true');
                sortItem.addEventListener('dragstart', function(e) {
                    e.dataTransfer.setData('text/plain', 'item');
                    sortItem.classList.add('ala-dragging');
                });
                sortItem.addEventListener('dragend', function() {
                    sortItem.classList.remove('ala-dragging');
                });
            }
        }

        renderState();
    }

    // ===================================================================
    // ACTIVITY RENDERER: CATEGORY SORT (4 categories)
    // ===================================================================
    function renderCategorySort(activity, container) {
        var unsorted = shuffle(activity.items.slice());
        var sorted = {};
        activity.categories.forEach(function(_, i) { sorted[i] = []; });
        var currentItemIdx = 0;

        function renderState() {
            var html = '<div class="ala-activity ala-categorysort">';
            html += '<h3 class="ala-activity-title">' + escapeHtml(activity.title) + '</h3>';
            if (activity.question) html += '<p class="ala-activity-question">' + escapeHtml(activity.question) + '</p>';
            html += '<p class="ala-activity-instruction">' + CONFIG.strings.categorysort_instructions + '</p>';

            html += '<div class="ala-categorysort-progress">';
            html += '<div class="ala-columnsort-progress-bar"><div class="ala-columnsort-progress-fill" style="width:' +
                (currentItemIdx / unsorted.length * 100) + '%"></div></div>';
            html += '<span>' + currentItemIdx + ' / ' + unsorted.length + '</span>';
            html += '</div>';

            // Current item to sort
            if (currentItemIdx < unsorted.length) {
                html += '<div class="ala-current-item-container">';
                html += '<div class="ala-current-item ala-animate-slide-in" id="ala-cat-item">' + escapeHtml(unsorted[currentItemIdx].text) + '</div>';
                html += '</div>';
            }

            // Category buckets
            html += '<div class="ala-categories-grid">';
            activity.categories.forEach(function(catName, catIdx) {
                html += '<div class="ala-category-bucket" data-cat="' + catIdx + '">';
                html += '<div class="ala-category-header">' + escapeHtml(catName) + '</div>';
                html += '<div class="ala-category-items">';
                (sorted[catIdx] || []).forEach(function(item) {
                    html += '<div class="ala-locked-item">' + escapeHtml(item.text) + '</div>';
                });
                html += '</div>';
                html += '</div>';
            });
            html += '</div>';

            html += '</div>';
            container.innerHTML = html;

            function handleCategoryChoice(chosenCat) {
                var item = unsorted[currentItemIdx];
                // item.category can be a numeric index (0-3) from AI generation or a
                // string category name for backward compatibility. Accept both forms.
                var correct = (item.category === chosenCat) ||
                              (item.category === activity.categories[chosenCat]);

                if (correct) {
                    playSound('lock');
                    sorted[chosenCat].push(item);
                    currentItemIdx++;
                    renderState();
                    if (currentItemIdx >= unsorted.length) {
                        showFeedback(true, CONFIG.strings.feedback_welldone);
                        progress[currentIndex] = true;
                        setTimeout(advanceToNext, 900);
                    }
                } else {
                    var catItem = document.getElementById('ala-cat-item');
                    if (catItem) {
                        catItem.classList.add('ala-shake');
                        setTimeout(function() { catItem.classList.remove('ala-shake'); }, 500);
                    }
                    showFeedback(false, CONFIG.strings.feedback_incorrect);
                }
            }

            // Bind category buckets as clickable + drag targets
            var buckets = container.querySelectorAll('.ala-category-bucket');
            buckets.forEach(function(bucket) {
                if (currentItemIdx < unsorted.length) {
                    bucket.classList.add('ala-bucket-clickable');
                    bucket.addEventListener('click', function() {
                        handleCategoryChoice(parseInt(bucket.getAttribute('data-cat')));
                    });
                }

                bucket.addEventListener('dragover', function(e) {
                    e.preventDefault();
                    bucket.classList.add('ala-drop-target');
                });
                bucket.addEventListener('dragleave', function() {
                    bucket.classList.remove('ala-drop-target');
                });
                bucket.addEventListener('drop', function(e) {
                    e.preventDefault();
                    bucket.classList.remove('ala-drop-target');
                    handleCategoryChoice(parseInt(bucket.getAttribute('data-cat')));
                });
            });

            // FIX-DRAG-TOUCH: always enable draggable (see column-sort comment above).
            var catItem = document.getElementById('ala-cat-item');
            if (catItem) {
                catItem.setAttribute('draggable', 'true');
                catItem.addEventListener('dragstart', function(e) {
                    e.dataTransfer.setData('text/plain', 'item');
                    catItem.classList.add('ala-dragging');
                });
            }
        }

        renderState();
    }

    // ===================================================================
    // ACTIVITY RENDERER: MATCHING
    // ===================================================================
    function renderMatching(activity, container) {
        var pairs = activity.pairs;
        var leftItems = shuffle(pairs.map(function(p, i) { return {text: p.left, pairIndex: i}; }));
        // v1.5.20: Guarantee shuffle is actually different from original order (fixes "not shuffling" on small pair counts).
        while (pairs.length > 1 && leftItems.every(function(item, idx) { return item.pairIndex === idx; })) {
            leftItems = shuffle(pairs.map(function(p, i) { return {text: p.left, pairIndex: i}; }));
        }
        var rightItems = shuffle(pairs.map(function(p, i) { return {text: p.right, pairIndex: i}; }));
        while (pairs.length > 1 && rightItems.every(function(item, idx) { return item.pairIndex === idx; })) {
            rightItems = shuffle(pairs.map(function(p, i) { return {text: p.right, pairIndex: i}; }));
        }
        var matched = {};
        var selectedLeft = null;
        var selectedRight = null;

        function renderState() {
            var html = '<div class="ala-activity ala-matching">';
            html += '<h3 class="ala-activity-title">' + escapeHtml(activity.title) + '</h3>';
            if (activity.question) html += '<p class="ala-activity-question">' + escapeHtml(activity.question) + '</p>';
            html += '<p class="ala-activity-instruction">' + CONFIG.strings.matching_instructions + '</p>';

            html += '<div class="ala-matching-layout">';

            // Left column
            html += '<div class="ala-matching-col ala-matching-left">';
            leftItems.forEach(function(item, idx) {
                var isMatched = matched[item.pairIndex] === true;
                var isSelected = selectedLeft === idx;
                var cls = 'ala-match-item ala-match-left';
                if (isMatched) cls += ' ala-matched';
                if (isSelected) cls += ' ala-selected';
                html += '<div class="' + cls + '" data-side="left" data-idx="' + idx + '" data-pair="' + item.pairIndex + '">' +
                    escapeHtml(item.text) + '</div>';
            });
            html += '</div>';

            // Right column
            html += '<div class="ala-matching-col ala-matching-right">';
            rightItems.forEach(function(item, idx) {
                var isMatched = matched[item.pairIndex] === true;
                var isSelected = selectedRight === idx;
                var cls = 'ala-match-item ala-match-right';
                if (isMatched) cls += ' ala-matched';
                if (isSelected) cls += ' ala-selected';
                html += '<div class="' + cls + '" data-side="right" data-idx="' + idx + '" data-pair="' + item.pairIndex + '">' +
                    escapeHtml(item.text) + '</div>';
            });
            html += '</div>';

            html += '</div></div>';
            container.innerHTML = html;

            // Bind click handlers
            var matchItems = container.querySelectorAll('.ala-match-item');
            matchItems.forEach(function(el) {
                if (el.classList.contains('ala-matched')) return;
                el.addEventListener('click', function() {
                    var side = this.getAttribute('data-side');
                    var idx = parseInt(this.getAttribute('data-idx'));

                    if (side === 'left') {
                        selectedLeft = idx;
                        container.querySelectorAll('.ala-match-left').forEach(function(e) { e.classList.remove('ala-selected'); });
                        this.classList.add('ala-selected');
                    } else {
                        selectedRight = idx;
                        container.querySelectorAll('.ala-match-right').forEach(function(e) { e.classList.remove('ala-selected'); });
                        this.classList.add('ala-selected');
                    }

                    // Check if both sides selected
                    if (selectedLeft !== null && selectedRight !== null) {
                        var leftPair = leftItems[selectedLeft].pairIndex;
                        var rightPair = rightItems[selectedRight].pairIndex;

                        if (leftPair === rightPair) {
                            matched[leftPair] = true;
                            playSound('lock');
                            selectedLeft = null;
                            selectedRight = null;

                            var allMatched = Object.keys(matched).length === pairs.length;
                            if (allMatched) {
                                renderState();
                                showFeedback(true, CONFIG.strings.feedback_welldone);
                                progress[currentIndex] = true;
                                setTimeout(advanceToNext, 900);
                            } else {
                                renderState();
                            }
                        } else {
                            showFeedback(false, CONFIG.strings.feedback_incorrect);
                            var leftEl = container.querySelector('.ala-match-left[data-idx="' + selectedLeft + '"]');
                            var rightEl = container.querySelector('.ala-match-right[data-idx="' + selectedRight + '"]');
                            if (leftEl) leftEl.classList.add('ala-shake');
                            if (rightEl) rightEl.classList.add('ala-shake');
                            selectedLeft = null;
                            selectedRight = null;
                            setTimeout(function() {
                                renderState();
                            }, 600);
                        }
                    }
                });
            });
        }

        renderState();
    }

    // ===================================================================
    // ACTIVITY RENDERER: ORDERING
    // ===================================================================
    function renderOrdering(activity, container) {
        var correctOrder = activity.items.slice();
        var currentOrder = shuffle(correctOrder.slice());
        // Ensure it's actually shuffled
        while (arraysEqual(currentOrder, correctOrder) && correctOrder.length > 1) {
            currentOrder = shuffle(correctOrder.slice());
        }
        var selectedIndex = null;

        function renderState() {
            var html = '<div class="ala-activity ala-ordering">';
            html += '<h3 class="ala-activity-title">' + escapeHtml(activity.title) + '</h3>';
            if (activity.question) html += '<p class="ala-activity-question">' + escapeHtml(activity.question) + '</p>';
            html += '<p class="ala-activity-instruction">' +
                escapeHtml(activity.instruction || CONFIG.strings.ordering_instructions) + '</p>';

            html += '<div class="ala-ordering-list" id="ala-order-list">';
            currentOrder.forEach(function(item, idx) {
                var isSelected = selectedIndex === idx;
                html += '<div class="ala-order-item' + (isSelected ? ' ala-selected' : '') + '" data-idx="' + idx + '"' + (isTouchDevice ? '' : ' draggable="true"') + '>';
                html += '<span class="ala-order-handle">';
                html += '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="5" r="1"/><circle cx="15" cy="5" r="1"/><circle cx="9" cy="12" r="1"/><circle cx="15" cy="12" r="1"/><circle cx="9" cy="19" r="1"/><circle cx="15" cy="19" r="1"/></svg>';
                html += '</span>';
                html += '<span class="ala-order-num">' + (idx + 1) + '</span>';
                html += '<span class="ala-order-text">' + escapeHtml(item) + '</span>';
                html += '</div>';
            });
            html += '</div>';

            html += '<div class="ala-ordering-actions">';
            html += '<button class="ala-btn ala-btn-primary" id="ala-check-order">' + CONFIG.strings.check_btn + '</button>';
            html += '</div>';

            html += '</div>';
            container.innerHTML = html;

            // Tap to reorder on mobile
            var orderItems = container.querySelectorAll('.ala-order-item');
            orderItems.forEach(function(el) {
                el.addEventListener('click', function() {
                    var idx = parseInt(this.getAttribute('data-idx'));

                    if (selectedIndex === null) {
                        selectedIndex = idx;
                        this.classList.add('ala-selected');
                    } else if (selectedIndex === idx) {
                        selectedIndex = null;
                        this.classList.remove('ala-selected');
                    } else {
                        // Swap items
                        var temp = currentOrder[selectedIndex];
                        currentOrder[selectedIndex] = currentOrder[idx];
                        currentOrder[idx] = temp;
                        selectedIndex = null;
                        renderState();
                    }
                });
            });

            // Drag and drop for desktop only
            if (!isTouchDevice) {
                var draggedIdx = null;
                orderItems.forEach(function(el) {
                    el.addEventListener('dragstart', function(e) {
                        draggedIdx = parseInt(this.getAttribute('data-idx'));
                        this.classList.add('ala-dragging');
                        e.dataTransfer.effectAllowed = 'move';
                        e.dataTransfer.setData('text/plain', draggedIdx.toString());
                    });
                    el.addEventListener('dragend', function() {
                        this.classList.remove('ala-dragging');
                        container.querySelectorAll('.ala-order-item').forEach(function(e) {
                            e.classList.remove('ala-drop-target');
                        });
                    });
                    el.addEventListener('dragover', function(e) {
                        e.preventDefault();
                        this.classList.add('ala-drop-target');
                    });
                    el.addEventListener('dragleave', function() {
                        this.classList.remove('ala-drop-target');
                    });
                    el.addEventListener('drop', function(e) {
                        e.preventDefault();
                        this.classList.remove('ala-drop-target');
                        var targetIdx = parseInt(this.getAttribute('data-idx'));
                        if (draggedIdx !== null && draggedIdx !== targetIdx) {
                            var temp = currentOrder[draggedIdx];
                            currentOrder[draggedIdx] = currentOrder[targetIdx];
                            currentOrder[targetIdx] = temp;
                            renderState();
                        }
                    });
                });
            }

            // Check button
            var checkBtn = document.getElementById('ala-check-order');
            if (checkBtn) {
                checkBtn.addEventListener('click', function() {
                    if (arraysEqual(currentOrder, correctOrder)) {
                        showFeedback(true, CONFIG.strings.feedback_welldone);
                        progress[currentIndex] = true;
                        // Mark all items as correct
                        container.querySelectorAll('.ala-order-item').forEach(function(el) {
                            el.classList.add('ala-correct');
                        });
                        setTimeout(advanceToNext, 800);
                    } else {
                        showFeedback(false, CONFIG.strings.feedback_incorrect);
                        // Highlight wrong positions
                        var items = container.querySelectorAll('.ala-order-item');
                        items.forEach(function(el, idx) {
                            if (currentOrder[idx] === correctOrder[idx]) {
                                el.classList.add('ala-correct');
                            } else {
                                el.classList.add('ala-incorrect');
                                el.classList.add('ala-shake');
                            }
                        });
                        setTimeout(function() {
                            items.forEach(function(el) {
                                el.classList.remove('ala-correct', 'ala-incorrect', 'ala-shake');
                            });
                        }, 1500);
                    }
                });
            }
        }

        renderState();
    }

    // ===================================================================
    // ACTIVITY RENDERER: FLASHCARDS
    // ===================================================================
    function renderFlashcards(activity, container) {
        var cards = activity.cards;
        var currentCardIdx = 0;
        var flipped = false;
        var animatingOut = false;

        function renderState() {
            var html = '<div class="ala-activity ala-flashcards">';
            html += '<h3 class="ala-activity-title">' + escapeHtml(activity.title) + '</h3>';
            if (activity.question) html += '<p class="ala-activity-question">' + escapeHtml(activity.question) + '</p>';
            html += '<p class="ala-activity-instruction">' + CONFIG.strings.flashcards_instructions + '</p>';

            html += '<div class="ala-flashcards-progress">';
            html += '<div class="ala-flashcards-dots">';
            for (var d = 0; d < cards.length; d++) {
                var dotClass = 'ala-fc-dot';
                if (d < currentCardIdx) dotClass += ' ala-fc-dot-done';
                else if (d === currentCardIdx) dotClass += ' ala-fc-dot-active';
                html += '<div class="' + dotClass + '"></div>';
            }
            html += '</div>';
            html += '<span class="ala-fc-counter">' + (currentCardIdx + 1) + ' / ' + cards.length + '</span>';
            html += '</div>';

            var card = cards[currentCardIdx];
            html += '<div class="ala-flashcard-container">';
            html += '<div class="ala-flashcard' + (flipped ? ' ala-flipped' : '') + '" id="ala-flashcard">';
            html += '<div class="ala-flashcard-face ala-flashcard-front">';
            html += '<div class="ala-flashcard-number">' + (currentCardIdx + 1) + '</div>';
            html += '<div class="ala-flashcard-label">' + (CONFIG.strings.flashcard_front || 'Question') + '</div>';
            html += '<div class="ala-flashcard-text">' + escapeHtml(card.front) + '</div>';
            html += '<div class="ala-flashcard-hint">';
            html += '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 15l-2 5L9 9l11 4-5 2z"/><path d="M14.25 14.25L18 10"/></svg> ';
            html += (CONFIG.strings.flashcard_tap || 'Tap to reveal answer');
            html += '</div>';
            html += '</div>';
            html += '<div class="ala-flashcard-face ala-flashcard-back">';
            html += '<div class="ala-flashcard-back-icon">';
            html += '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>';
            html += '</div>';
            html += '<div class="ala-flashcard-label">' + (CONFIG.strings.flashcard_back || 'Answer') + '</div>';
            html += '<div class="ala-flashcard-text">' + escapeHtml(card.back) + '</div>';
            html += '</div>';
            html += '</div>';
            html += '</div>';

            if (flipped) {
                var isLast = currentCardIdx >= cards.length - 1;
                html += '<div class="ala-flashcard-actions ala-fc-actions-appear">';
                html += '<button class="ala-btn ala-btn-primary ala-fc-next-btn" id="ala-flashcard-next">';
                if (isLast) {
                    html += '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/></svg> ';
                    html += (CONFIG.strings.flashcard_done || 'All Done!');
                } else {
                    html += (CONFIG.strings.next_card_btn || 'Next Card');
                    html += ' <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>';
                }
                html += '</button>';
                html += '</div>';
            }

            html += '</div>';
            container.innerHTML = html;

            var flashcardEl = document.getElementById('ala-flashcard');
            if (flashcardEl && !flipped) {
                flashcardEl.addEventListener('click', function() {
                    if (animatingOut) return;
                    flipped = true;
                    playSound('flip');
                    this.classList.add('ala-flipped');
                    setTimeout(function() {
                        playSound('cardDone');
                    }, 400);
                    setTimeout(function() {
                        renderState();
                    }, 500);
                });
            }

            var nextBtn = document.getElementById('ala-flashcard-next');
            if (nextBtn) {
                nextBtn.addEventListener('click', function() {
                    if (animatingOut) return;
                    animatingOut = true;
                    var cardContainer = container.querySelector('.ala-flashcard-container');
                    if (cardContainer) cardContainer.classList.add('ala-fc-slide-out');
                    playSound('swoosh');

                    setTimeout(function() {
                        animatingOut = false;
                        if (currentCardIdx < cards.length - 1) {
                            currentCardIdx++;
                            flipped = false;
                            renderState();
                            var newContainer = container.querySelector('.ala-flashcard-container');
                            if (newContainer) {
                                newContainer.classList.add('ala-fc-slide-in');
                                setTimeout(function() { newContainer.classList.remove('ala-fc-slide-in'); }, 350);
                            }
                        } else {
                            playSound('correct');
                            showFeedback(true, CONFIG.strings.feedback_welldone);
                            progress[currentIndex] = true;
                            setTimeout(advanceToNext, 800);
                        }
                    }, 300);
                });
            }
        }

        renderState();
    }

    // ===================================================================
    // ACTIVITY RENDERER: TRUE/FALSE SWIPE
    // ===================================================================
    function renderTrueFalseSwipe(activity, container) {
        var statements = activity.statements || [];
        var currentStmtIdx = 0;
        var correctCount = 0;
        var showingExplanation = false;
        var lastAnswerCorrect = false;
        var lastChosenTrue = null; // v1.5.13: track which button the student pressed

        function renderState() {
            var html = '<div class="ala-activity ala-truefalseswipe">';
            html += '<h3 class="ala-activity-title">' + escapeHtml(activity.title) + '</h3>';
            if (activity.question) html += '<p class="ala-activity-question">' + escapeHtml(activity.question) + '</p>';
            html += '<p class="ala-activity-instruction">' + CONFIG.strings.truefalseswipe_instructions + '</p>';

            html += '<div class="ala-tfs-progress">';
            html += '<div class="ala-columnsort-progress-bar"><div class="ala-columnsort-progress-fill" style="width:' +
                (currentStmtIdx / statements.length * 100) + '%"></div></div>';
            html += '<span>' + currentStmtIdx + ' / ' + statements.length + '</span>';
            html += '</div>';

            if (currentStmtIdx < statements.length) {
                var stmt = statements[currentStmtIdx];
                html += '<div class="ala-tfs-card-container">';
                html += '<div class="ala-tfs-card ala-animate-slide-in" id="ala-tfs-card">';
                html += '<div class="ala-tfs-statement">' + escapeHtml(stmt.text) + '</div>';

                if (showingExplanation) {
                    // v1.5.23 BUG-TF-FEEDBACK-POS: Correct/Incorrect label moved INSIDE the
                    // card above the explanation (was injected between the True/False buttons
                    // in the buttons row, so it appeared between the option boxes  -  wrong).
                    html += '<div class="ala-tfs-mid-result ' +
                        (lastAnswerCorrect ? 'ala-tfs-result-correct' : 'ala-tfs-result-incorrect') + '">';
                    if (lastAnswerCorrect) {
                        html += '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>';
                        html += (CONFIG.strings.correct || 'Correct!');
                    } else {
                        html += '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>';
                        html += (CONFIG.strings.incorrect || 'Incorrect!');
                    }
                    html += '</div>';
                    html += '<div class="ala-tfs-explanation">';
                    html += '<div class="ala-tfs-explanation-answer">';
                    html += '<span class="ala-tfs-answer-label">' + (CONFIG.strings.truefalse_answer || 'Answer') + ': </span>';
                    html += (stmt.correct ? '<span class="ala-tfs-true-badge">' + (CONFIG.strings.truefalse_true || 'True') + '</span>' :
                        '<span class="ala-tfs-false-badge">' + (CONFIG.strings.truefalse_false || 'False') + '</span>');
                    html += '</div>';
                    html += '<p class="ala-tfs-explanation-text">' + escapeHtml(stmt.explanation) + '</p>';
                    html += '</div>';
                }

                html += '</div>';
                html += '</div>';

                if (!showingExplanation) {
                    // Unanswered: show normal True/False buttons
                    html += '<div class="ala-tfs-buttons">';
                    html += '<button class="ala-btn ala-tfs-btn-false" id="ala-tfs-false">';
                    html += '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>';
                    html += ' ' + (CONFIG.strings.truefalse_false || 'False');
                    html += '</button>';
                    html += '<button class="ala-btn ala-tfs-btn-true" id="ala-tfs-true">';
                    html += '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>';
                    html += ' ' + (CONFIG.strings.truefalse_true || 'True');
                    html += '</button>';
                    html += '</div>';
                } else {
                    // v1.5.13: After answering, keep both buttons visible in result state
                    // so the student can see exactly which option was correct/incorrect.
                    var stmtCorrectVal = stmt.correct === true || stmt.correct === 'true';
                    var falseCorrect  = !stmtCorrectVal;
                    var falseChosen   = (lastChosenTrue === false);
                    var trueCorrect   = stmtCorrectVal;
                    var trueChosen    = (lastChosenTrue === true);

                    var falseBtnCls = 'ala-btn ala-tfs-btn-false ala-tfs-btn-result';
                    var trueBtnCls  = 'ala-btn ala-tfs-btn-true ala-tfs-btn-result';
                    var falseIconHtml = '';
                    var trueIconHtml  = '';

                    if (falseCorrect) {
                        falseBtnCls += ' ala-tfs-btn-result-correct';
                        // Tick SVG
                        falseIconHtml = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>';
                    } else if (falseChosen) {
                        falseBtnCls += ' ala-tfs-btn-result-incorrect';
                        // Cross SVG
                        falseIconHtml = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>';
                    } else {
                        falseBtnCls += ' ala-tfs-btn-result-neutral';
                    }

                    if (trueCorrect) {
                        trueBtnCls += ' ala-tfs-btn-result-correct';
                        trueIconHtml = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>';
                    } else if (trueChosen) {
                        trueBtnCls += ' ala-tfs-btn-result-incorrect';
                        trueIconHtml = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>';
                    } else {
                        trueBtnCls += ' ala-tfs-btn-result-neutral';
                    }

                    // v1.5.23: 2-button result row (mid-result label moved into card above).
                    html += '<div class="ala-tfs-buttons ala-tfs-buttons-result">';
                    html += '<button class="' + falseBtnCls + '" disabled>';
                    if (falseIconHtml) html += falseIconHtml + ' ';
                    html += (CONFIG.strings.truefalse_false || 'False');
                    html += '</button>';
                    html += '<button class="' + trueBtnCls + '" disabled>';
                    if (trueIconHtml) html += trueIconHtml + ' ';
                    html += (CONFIG.strings.truefalse_true || 'True');
                    html += '</button>';
                    html += '</div>';

                    html += '<div class="ala-tfs-buttons ala-tfs-continue-row">';
                    html += '<button class="ala-btn ala-btn-primary" id="ala-tfs-continue">';
                    html += (currentStmtIdx < statements.length - 1 ? (CONFIG.strings.next_btn || 'Next') : (CONFIG.strings.flashcard_done || 'Done'));
                    html += ' <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>';
                    html += '</button>';
                    html += '</div>';
                }
            }

            html += '</div>';
            container.innerHTML = html;

            function handleAnswer(chosenTrue) {
                var stmt = statements[currentStmtIdx];
                var stmtCorrect = stmt.correct === true || stmt.correct === 'true';
                var correct = chosenTrue === stmtCorrect;
                lastAnswerCorrect = correct;
                lastChosenTrue = chosenTrue; // v1.5.13

                if (correct) {
                    playSound('correct');
                    correctCount++;
                } else {
                    playSound('wrong');
                    var card = document.getElementById('ala-tfs-card');
                    if (card) {
                        card.classList.add('ala-shake');
                    }
                }

                showingExplanation = true;
                renderState();
            }

            var falseBtn = document.getElementById('ala-tfs-false');
            if (falseBtn) {
                falseBtn.addEventListener('click', function() { handleAnswer(false); });
            }
            var trueBtn = document.getElementById('ala-tfs-true');
            if (trueBtn) {
                trueBtn.addEventListener('click', function() { handleAnswer(true); });
            }

            var continueBtn = document.getElementById('ala-tfs-continue');
            if (continueBtn) {
                continueBtn.addEventListener('click', function() {
                    currentStmtIdx++;
                    showingExplanation = false;
                    lastChosenTrue = null; // v1.5.13: reset for next question
                    if (currentStmtIdx >= statements.length) {
                        var allCorrect = correctCount === statements.length;
                        if (!allCorrect) hadWrongAnswers = true;
                        // BUG-ALA-SOUND fix: previously called showFeedback(allCorrect, ...)
                        // which played playSound('wrong') whenever any answer was incorrect,
                        // even though the student has just COMPLETED the activity. At completion
                        // the sound should always be positive. Always pass true so the 'correct'
                        // sound plays and the green checkmark is shown regardless of accuracy.
                        showFeedback(true, CONFIG.strings.truefalse_activity_complete || 'Activity done.');
                        progress[currentIndex] = true;
                        setTimeout(advanceToNext, 800);
                    } else {
                        renderState();
                    }
                });
            }
        }

        renderState();
    }

    // ===================================================================
    // ACTIVITY RENDERER: FILL IN THE BLANK
    // ===================================================================
    function renderFillInBlank(activity, container) {
        var blanks = activity.blanks;
        var allWords = shuffle(blanks.map(function(b) { return b.answer; }).concat(activity.distractors || []));
        var filledBlanks = {};
        var selectedBlankPos = null;

        function renderState() {
            var html = '<div class="ala-activity ala-fillinblank">';
            html += '<h3 class="ala-activity-title">' + escapeHtml(activity.title) + '</h3>';
            if (activity.question) html += '<p class="ala-activity-question">' + escapeHtml(activity.question) + '</p>';
            html += '<p class="ala-activity-instruction">' + CONFIG.strings.fillinblank_instructions + '</p>';

            // Render text with blanks
            html += '<div class="ala-fib-text">';
            var text = activity.text;
            for (var i = blanks.length; i >= 1; i--) {
                var placeholder = '___' + i + '___';
                var pos = i - 1;
                var filled = filledBlanks[pos];
                var blankHtml;
                if (filled) {
                    var isCorrect = filled === blanks[pos].answer;
                    blankHtml = '<span class="ala-fib-blank ala-fib-filled' +
                        (filled && filledBlanks[pos + '_checked'] ? (isCorrect ? ' ala-correct' : ' ala-incorrect') : '') +
                        (selectedBlankPos === pos ? ' ala-selected' : '') +
                        '" data-pos="' + pos + '">' + escapeHtml(filled) + '</span>';
                } else {
                    blankHtml = '<span class="ala-fib-blank ala-fib-empty' +
                        (selectedBlankPos === pos ? ' ala-selected' : '') +
                        '" data-pos="' + pos + '">' + (pos + 1) + '</span>';
                }
                text = text.replace(placeholder, blankHtml);
            }
            html += '<p class="ala-fib-passage">' + text + '</p>';
            html += '</div>';

            // Word bank
            html += '<div class="ala-fib-wordbank">';
            html += '<div class="ala-fib-wordbank-label">' + (CONFIG.strings.fillinblank_wordbank || 'Word Bank') + '</div>';
            html += '<div class="ala-fib-words">';
            var usedWords = {};
            for (var key in filledBlanks) {
                if (key.indexOf('_checked') === -1 && filledBlanks[key]) {
                    usedWords[filledBlanks[key]] = true;
                }
            }
            allWords.forEach(function(word) {
                var isUsed = usedWords[word] === true;
                html += '<button class="ala-fib-word' + (isUsed ? ' ala-fib-word-used' : '') + '" data-word="' +
                    escapeAttr(word) + '"' + (isUsed ? ' disabled' : '') + '>' + escapeHtml(word) + '</button>';
            });
            html += '</div>';
            html += '</div>';

            // Check button
            var allFilled = true;
            for (var b = 0; b < blanks.length; b++) {
                if (!filledBlanks[b]) { allFilled = false; break; }
            }
            if (allFilled) {
                html += '<div class="ala-fib-actions">';
                html += '<button class="ala-btn ala-btn-primary" id="ala-fib-check">' + CONFIG.strings.check_btn + '</button>';
                html += '</div>';
            }

            html += '</div>';
            container.innerHTML = html;

            // Bind blank clicks
            var blankEls = container.querySelectorAll('.ala-fib-blank');
            blankEls.forEach(function(el) {
                el.addEventListener('click', function() {
                    var pos = parseInt(this.getAttribute('data-pos'));
                    if (filledBlanks[pos + '_checked']) return;
                    if (filledBlanks[pos]) {
                        delete filledBlanks[pos];
                        selectedBlankPos = pos;
                        renderState();
                    } else {
                        selectedBlankPos = pos;
                        renderState();
                    }
                });
            });

            // Bind word bank clicks
            var wordEls = container.querySelectorAll('.ala-fib-word:not([disabled])');
            wordEls.forEach(function(el) {
                el.addEventListener('click', function() {
                    var word = this.getAttribute('data-word');
                    if (selectedBlankPos !== null) {
                        filledBlanks[selectedBlankPos] = word;
                        selectedBlankPos = null;
                        renderState();
                    } else {
                        for (var b = 0; b < blanks.length; b++) {
                            if (!filledBlanks[b]) {
                                filledBlanks[b] = word;
                                break;
                            }
                        }
                        renderState();
                    }
                });
            });

            // Bind check button
            var checkBtn = document.getElementById('ala-fib-check');
            if (checkBtn) {
                checkBtn.addEventListener('click', function() {
                    var allCorrect = true;
                    for (var b = 0; b < blanks.length; b++) {
                        filledBlanks[b + '_checked'] = true;
                        if (filledBlanks[b] !== blanks[b].answer) {
                            allCorrect = false;
                        }
                    }
                    if (allCorrect) {
                        showFeedback(true, CONFIG.strings.feedback_welldone);
                        progress[currentIndex] = true;
                        renderState();
                        setTimeout(advanceToNext, 800);
                    } else {
                        showFeedback(false, CONFIG.strings.feedback_incorrect);
                        renderState();
                        setTimeout(function() {
                            for (var b = 0; b < blanks.length; b++) {
                                if (filledBlanks[b] !== blanks[b].answer) {
                                    delete filledBlanks[b];
                                }
                                delete filledBlanks[b + '_checked'];
                            }
                            selectedBlankPos = null;
                            renderState();
                        }, 1500);
                    }
                });
            }
        }

        renderState();
    }

    // ========== UTILITY ==========
    function arraysEqual(a, b) {
        if (a.length !== b.length) return false;
        for (var i = 0; i < a.length; i++) {
            if (a[i] !== b[i]) return false;
        }
        return true;
    }

    function escapeHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    // ===================================================================
    // RENDER ACTIVITY (dispatcher)
    // ===================================================================
    function renderActivity(index) {
        // v1.5.43 FIX-WELLDONE-OVERLAP: always clear the feedback toast before rendering a new activity
        // so the "Well done!" green toast never bleeds over the next activity's UI.
        if (feedbackTimer !== null) {
            clearTimeout(feedbackTimer);
            feedbackTimer = null;
        }
        hideFeedbackEl(document.getElementById(isPreviewMode ? 'ala-preview-feedback' : 'ala-player-feedback'));

        var containerId = isPreviewMode ? 'ala-preview-content' : 'ala-player-content';
        var container = document.getElementById(containerId);
        if (!container || !activities[index]) return;

        container.style.display = 'block';
        var activity = activities[index];

        if (!isPreviewMode) {
            updateProgressBar();
        }

        container.classList.remove('ala-animate-slide-in');
        void container.offsetWidth;
        container.classList.add('ala-animate-slide-in');

        switch (activity.type) {
            case 'cardselect':
                renderCardSelect(activity, container);
                break;
            case 'columnsort':
                renderColumnSort(activity, container);
                break;
            case 'categorysort':
                renderCategorySort(activity, container);
                break;
            case 'matching':
                renderMatching(activity, container);
                break;
            case 'ordering':
                renderOrdering(activity, container);
                break;
            case 'flashcards':
                renderFlashcards(activity, container);
                break;
            case 'truefalseswipe':
                renderTrueFalseSwipe(activity, container);
                break;
            case 'fillinblank':
                renderFillInBlank(activity, container);
                break;
            default:
                container.innerHTML = '<p>Unknown activity type: ' + activity.type + '</p>';
        }

        // v1.6.8 FIX-AUDIO-BLEED: Stop any voiceover from the previous activity before
        // starting a new one. Without this, the old TTS audio kept playing through the
        // transition and overlapped the next activity's narration.
        if (currentVoiceoverSrc) {
            try { currentVoiceoverSrc.stop(); } catch(e) {}
            currentVoiceoverSrc = null;
        }

        // Auto-play voiceover audio if present on this activity
        if (activity.audioData && !isPreviewMode) {
            try {
                var ctx = getAudioCtx();
                var raw = atob(activity.audioData);
                var buf = new ArrayBuffer(raw.length);
                var view = new Uint8Array(buf);
                for (var bi = 0; bi < raw.length; bi++) { view[bi] = raw.charCodeAt(bi); }
                ctx.decodeAudioData(buf, function(decoded) {
                    var src = ctx.createBufferSource();
                    src.buffer = decoded;
                    src.connect(ctx.destination);
                    currentVoiceoverSrc = src;
                    src.onended = function() { currentVoiceoverSrc = null; };
                    src.start(0);
                }, function() { /* decode error: skip silently */ });
            } catch (e) { /* audio not critical */ }
        }
    }

    // ========== TEACHER EDIT MODE ==========
    function openTeacherEdit(index) {
        var activity = activities[index];
        if (!activity) return;

        var manageSection = document.getElementById('ala-manage-section');
        var editSection = document.getElementById('ala-teacher-edit');
        var editContent = document.getElementById('ala-edit-content');

        if (manageSection) manageSection.style.display = 'none';
        if (editSection) editSection.style.display = 'block';

        var typeLabel = activity.type.replace('cardselect', 'Card Select')
            .replace('columnsort', 'Column Sort')
            .replace('categorysort', 'Category Sort')
            .replace('matching', 'Matching Pairs')
            .replace('ordering', 'Put in Order')
            .replace('flashcards', 'Flashcards')
            .replace('truefalseswipe', 'True or False')
            .replace('fillinblank', 'Fill in the Blank');

        var editLabelEl = document.getElementById('ala-edit-label');
        if (editLabelEl) {
            editLabelEl.textContent = 'Editing: ' + typeLabel + ' - ' + (activity.title || '');
        }

        if (!editContent) return;

        var html = '<div class="ala-edit-form">';
        html += '<div class="ala-edit-field">';
        html += '<label class="ala-edit-field-label">Title</label>';
        html += '<input type="text" class="ala-edit-input" id="ala-edit-title" value="' + escapeAttr(activity.title || '') + '" />';
        html += '</div>';

        html += '<div class="ala-edit-field">';
        html += '<label class="ala-edit-field-label">Question</label>';
        html += '<input type="text" class="ala-edit-input" id="ala-edit-question" value="' + escapeAttr(activity.question || '') + '" />';
        html += '</div>';

        if (activity.type === 'cardselect') {
            html += '<div class="ala-edit-field">';
            html += '<label class="ala-edit-field-label">Cards</label>';
            activity.cards.forEach(function(card, ci) {
                html += '<div class="ala-edit-subgroup">';
                html += '<div class="ala-edit-subgroup-header">Card ' + (ci + 1) + (ci === activity.correctIndex ? ' (correct answer)' : '') + '</div>';
                html += '<input type="text" class="ala-edit-input ala-edit-card-label" data-ci="' + ci + '" placeholder="Label" value="' + escapeAttr(card.label || '') + '" />';
                html += '<input type="text" class="ala-edit-input ala-edit-card-desc" data-ci="' + ci + '" placeholder="Description" value="' + escapeAttr(card.description || '') + '" />';
                html += '</div>';
            });
            html += '</div>';
        } else if (activity.type === 'columnsort') {
            html += '<div class="ala-edit-field">';
            html += '<label class="ala-edit-field-label">Column A Name</label>';
            html += '<input type="text" class="ala-edit-input" id="ala-edit-colA" value="' + escapeAttr(activity.columnA || '') + '" />';
            html += '</div>';
            html += '<div class="ala-edit-field">';
            html += '<label class="ala-edit-field-label">Column B Name</label>';
            html += '<input type="text" class="ala-edit-input" id="ala-edit-colB" value="' + escapeAttr(activity.columnB || '') + '" />';
            html += '</div>';
            html += '<div class="ala-edit-field">';
            html += '<label class="ala-edit-field-label">Items</label>';
            activity.items.forEach(function(item, ii) {
                html += '<div class="ala-edit-subgroup">';
                html += '<div class="ala-edit-subgroup-header">Item ' + (ii + 1) + ' (belongs to: ' + item.column + ')</div>';
                html += '<input type="text" class="ala-edit-input ala-edit-sort-item" data-ii="' + ii + '" value="' + escapeAttr(item.text || '') + '" />';
                html += '</div>';
            });
            html += '</div>';
        } else if (activity.type === 'categorysort') {
            html += '<div class="ala-edit-field">';
            html += '<label class="ala-edit-field-label">Categories</label>';
            activity.categories.forEach(function(cat, ci) {
                html += '<div class="ala-edit-subgroup">';
                html += '<div class="ala-edit-subgroup-header">Category ' + (ci + 1) + '</div>';
                html += '<input type="text" class="ala-edit-input ala-edit-category" data-ci="' + ci + '" value="' + escapeAttr(cat) + '" />';
                html += '</div>';
            });
            html += '</div>';
            html += '<div class="ala-edit-field">';
            html += '<label class="ala-edit-field-label">Items</label>';
            activity.items.forEach(function(item, ii) {
                // Resolve numeric index  ->  name; string category displayed directly.
                var catLabel = typeof item.category === 'number'
                    ? (activity.categories[item.category] !== undefined ? activity.categories[item.category] : String(item.category))
                    : (item.category != null ? String(item.category) : '');
                html += '<div class="ala-edit-subgroup">';
                html += '<div class="ala-edit-subgroup-header">Item ' + (ii + 1) + ' (belongs to: ' + escapeHtml(catLabel) + ')</div>';
                html += '<input type="text" class="ala-edit-input ala-edit-catitem" data-ii="' + ii + '" value="' + escapeAttr(item.text || '') + '" />';
                html += '</div>';
            });
            html += '</div>';
        } else if (activity.type === 'matching') {
            html += '<div class="ala-edit-field">';
            html += '<label class="ala-edit-field-label">Matching Pairs</label>';
            activity.pairs.forEach(function(pair, pi) {
                html += '<div class="ala-edit-subgroup">';
                html += '<div class="ala-edit-subgroup-header">Pair ' + (pi + 1) + '</div>';
                html += '<div class="ala-edit-pair-row">';
                html += '<input type="text" class="ala-edit-input ala-edit-pair-left" data-pi="' + pi + '" placeholder="Left" value="' + escapeAttr(pair.left || '') + '" />';
                html += '<span class="ala-edit-pair-arrow">&#8596;</span>';
                html += '<input type="text" class="ala-edit-input ala-edit-pair-right" data-pi="' + pi + '" placeholder="Right" value="' + escapeAttr(pair.right || '') + '" />';
                html += '</div>';
                html += '</div>';
            });
            html += '</div>';
        } else if (activity.type === 'ordering') {
            html += '<div class="ala-edit-field">';
            html += '<label class="ala-edit-field-label">Items (in correct order)</label>';
            activity.items.forEach(function(item, ii) {
                html += '<div class="ala-edit-subgroup">';
                html += '<div class="ala-edit-subgroup-header">Step ' + (ii + 1) + '</div>';
                html += '<input type="text" class="ala-edit-input ala-edit-order-item" data-ii="' + ii + '" value="' + escapeAttr(item) + '" />';
                html += '</div>';
            });
            html += '</div>';
        } else if (activity.type === 'flashcards') {
            html += '<div class="ala-edit-field">';
            html += '<label class="ala-edit-field-label">Flashcards</label>';
            activity.cards.forEach(function(card, ci) {
                html += '<div class="ala-edit-subgroup">';
                html += '<div class="ala-edit-subgroup-header">Card ' + (ci + 1) + '</div>';
                html += '<input type="text" class="ala-edit-input ala-edit-fc-front" data-ci="' + ci + '" placeholder="Front" value="' + escapeAttr(card.front || '') + '" />';
                html += '<input type="text" class="ala-edit-input ala-edit-fc-back" data-ci="' + ci + '" placeholder="Back" value="' + escapeAttr(card.back || '') + '" />';
                html += '</div>';
            });
            html += '</div>';
        } else if (activity.type === 'truefalseswipe') {
            html += '<div class="ala-edit-field">';
            html += '<label class="ala-edit-field-label">Statements</label>';
            activity.statements.forEach(function(stmt, si) {
                html += '<div class="ala-edit-subgroup">';
                html += '<div class="ala-edit-subgroup-header">Statement ' + (si + 1) + ' (' + (stmt.correct ? 'True' : 'False') + ')</div>';
                html += '<input type="text" class="ala-edit-input ala-edit-tfs-text" data-si="' + si + '" placeholder="Statement" value="' + escapeAttr(stmt.text || '') + '" />';
                html += '<input type="text" class="ala-edit-input ala-edit-tfs-explanation" data-si="' + si + '" placeholder="Explanation" value="' + escapeAttr(stmt.explanation || '') + '" />';
                html += '</div>';
            });
            html += '</div>';
        } else if (activity.type === 'fillinblank') {
            html += '<div class="ala-edit-field">';
            html += '<label class="ala-edit-field-label">Sentence (use ___1___, ___2___ etc for blanks)</label>';
            html += '<textarea class="ala-edit-input" id="ala-edit-fib-text" rows="3">' + escapeHtml(activity.text || '') + '</textarea>';
            html += '</div>';
            html += '<div class="ala-edit-field">';
            html += '<label class="ala-edit-field-label">Blank Answers</label>';
            activity.blanks.forEach(function(blank, bi) {
                html += '<div class="ala-edit-subgroup">';
                html += '<div class="ala-edit-subgroup-header">Blank ' + (bi + 1) + '</div>';
                html += '<input type="text" class="ala-edit-input ala-edit-fib-answer" data-bi="' + bi + '" value="' + escapeAttr(blank.answer || '') + '" />';
                html += '</div>';
            });
            html += '</div>';
        }

        html += '<div class="ala-edit-actions">';
        html += '<button type="button" id="ala-edit-save-btn" class="ala-btn ala-btn-primary">Save Changes</button>';
        html += '<button type="button" id="ala-edit-cancel-btn" class="ala-btn ala-btn-outline">Cancel</button>';
        html += '</div>';
        html += '</div>';

        editContent.innerHTML = html;

        // Bind save
        var saveBtn = document.getElementById('ala-edit-save-btn');
        if (saveBtn) {
            saveBtn.addEventListener('click', function() {
                saveActivityEdit(index);
            });
        }

        // Bind cancel
        var cancelBtn = document.getElementById('ala-edit-cancel-btn');
        if (cancelBtn) {
            cancelBtn.addEventListener('click', function() {
                closeTeacherEdit();
            });
        }
    }

    function saveActivityEdit(index) {
        var activity = JSON.parse(JSON.stringify(activities[index]));

        var titleEl = document.getElementById('ala-edit-title');
        if (titleEl) activity.title = titleEl.value.trim();

        var questionEl = document.getElementById('ala-edit-question');
        if (questionEl) activity.question = questionEl.value.trim();

        if (activity.type === 'cardselect') {
            var labelEls = document.querySelectorAll('.ala-edit-card-label');
            var descEls = document.querySelectorAll('.ala-edit-card-desc');
            labelEls.forEach(function(el) {
                var ci = parseInt(el.getAttribute('data-ci'));
                if (activity.cards[ci]) activity.cards[ci].label = el.value.trim();
            });
            descEls.forEach(function(el) {
                var ci = parseInt(el.getAttribute('data-ci'));
                if (activity.cards[ci]) activity.cards[ci].description = el.value.trim();
            });
        } else if (activity.type === 'columnsort') {
            var colAEl = document.getElementById('ala-edit-colA');
            var colBEl = document.getElementById('ala-edit-colB');
            if (colAEl) activity.columnA = colAEl.value.trim();
            if (colBEl) activity.columnB = colBEl.value.trim();

            var sortEls = document.querySelectorAll('.ala-edit-sort-item');
            sortEls.forEach(function(el) {
                var ii = parseInt(el.getAttribute('data-ii'));
                if (activity.items[ii]) activity.items[ii].text = el.value.trim();
            });
        } else if (activity.type === 'categorysort') {
            var catEls = document.querySelectorAll('.ala-edit-category');
            catEls.forEach(function(el) {
                var ci = parseInt(el.getAttribute('data-ci'));
                var oldCat = activity.categories[ci];
                var newCat = el.value.trim();
                activity.categories[ci] = newCat;
                activity.items.forEach(function(item) {
                    if (item.category === oldCat) item.category = newCat;
                });
            });
            var catItemEls = document.querySelectorAll('.ala-edit-catitem');
            catItemEls.forEach(function(el) {
                var ii = parseInt(el.getAttribute('data-ii'));
                if (activity.items[ii]) activity.items[ii].text = el.value.trim();
            });
        } else if (activity.type === 'matching') {
            var leftEls = document.querySelectorAll('.ala-edit-pair-left');
            var rightEls = document.querySelectorAll('.ala-edit-pair-right');
            leftEls.forEach(function(el) {
                var pi = parseInt(el.getAttribute('data-pi'));
                if (activity.pairs[pi]) activity.pairs[pi].left = el.value.trim();
            });
            rightEls.forEach(function(el) {
                var pi = parseInt(el.getAttribute('data-pi'));
                if (activity.pairs[pi]) activity.pairs[pi].right = el.value.trim();
            });
        } else if (activity.type === 'ordering') {
            var orderEls = document.querySelectorAll('.ala-edit-order-item');
            orderEls.forEach(function(el) {
                var ii = parseInt(el.getAttribute('data-ii'));
                activity.items[ii] = el.value.trim();
            });
        } else if (activity.type === 'flashcards') {
            var fcFrontEls = document.querySelectorAll('.ala-edit-fc-front');
            var fcBackEls = document.querySelectorAll('.ala-edit-fc-back');
            fcFrontEls.forEach(function(el) {
                var ci = parseInt(el.getAttribute('data-ci'));
                if (activity.cards[ci]) activity.cards[ci].front = el.value.trim();
            });
            fcBackEls.forEach(function(el) {
                var ci = parseInt(el.getAttribute('data-ci'));
                if (activity.cards[ci]) activity.cards[ci].back = el.value.trim();
            });
        } else if (activity.type === 'truefalseswipe') {
            var tfsTextEls = document.querySelectorAll('.ala-edit-tfs-text');
            var tfsExpEls = document.querySelectorAll('.ala-edit-tfs-explanation');
            tfsTextEls.forEach(function(el) {
                var si = parseInt(el.getAttribute('data-si'));
                if (activity.statements[si]) activity.statements[si].text = el.value.trim();
            });
            tfsExpEls.forEach(function(el) {
                var si = parseInt(el.getAttribute('data-si'));
                if (activity.statements[si]) activity.statements[si].explanation = el.value.trim();
            });
        } else if (activity.type === 'fillinblank') {
            var fibTextEl = document.getElementById('ala-edit-fib-text');
            if (fibTextEl) activity.text = fibTextEl.value.trim();
            var fibAnswerEls = document.querySelectorAll('.ala-edit-fib-answer');
            fibAnswerEls.forEach(function(el) {
                var bi = parseInt(el.getAttribute('data-bi'));
                if (activity.blanks[bi]) activity.blanks[bi].answer = el.value.trim();
            });
        }

        var saveBtn = document.getElementById('ala-edit-save-btn');
        if (saveBtn) {
            saveBtn.disabled = true;
            saveBtn.textContent = 'Saving...';
        }

        ajaxCall('saveactivity', {
            activityindex: index,
            activityjson: JSON.stringify(activity)
        }, function(result) {
            if (result.ok) {
                activities[index] = activity;
                closeTeacherEdit();
                refreshActivityList();
            } else {
                if (saveBtn) {
                    saveBtn.disabled = false;
                    saveBtn.textContent = 'Save Changes';
                }
                alert(result.error || 'Failed to save');
            }
        });
    }

    function closeTeacherEdit() {
        var manageSection = document.getElementById('ala-manage-section');
        var editSection = document.getElementById('ala-teacher-edit');
        var editContent = document.getElementById('ala-edit-content');

        if (editSection) editSection.style.display = 'none';
        if (manageSection) manageSection.style.display = 'block';
        if (editContent) editContent.innerHTML = '';
    }

    function refreshActivityList() {
        var previewEl = document.getElementById('ala-activities-preview');
        if (!previewEl) return;

        var html = buildEtaBannerHtml(activities);
        activities.forEach(function(act, idx) {
            var typeLabel = act.type.replace('cardselect', 'Card Select')
                .replace('columnsort', 'Column Sort')
                .replace('categorysort', 'Category Sort')
                .replace('matching', 'Matching Pairs')
                .replace('ordering', 'Put in Order')
                .replace('flashcards', 'Flashcards')
                .replace('truefalseswipe', 'True or False')
                .replace('fillinblank', 'Fill in the Blank');
            html += '<div class="ala-preview-item ala-preview-clickable" data-activity-index="' + idx + '">';
            html += '<span class="ala-preview-num">' + (idx + 1) + '</span>';
            html += '<span class="ala-preview-type">' + typeLabel + '</span>';
            html += '<span class="ala-preview-title">' + escapeHtml(act.title) + '</span>';
            html += '<span class="ala-preview-edit" data-edit-index="' + idx + '" title="Edit activity"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/></svg></span>';
            html += '<span class="ala-preview-play" data-preview-index="' + idx + '"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="6 3 20 12 6 21 6 3"/></svg></span>';
            html += '</div>';
        });
        previewEl.innerHTML = html;

        bindPreviewItemClicks(previewEl);
    }

    function bindPreviewItemClicks(previewEl) {
        var editBtns = previewEl.querySelectorAll('.ala-preview-edit');
        editBtns.forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                var idx = parseInt(this.getAttribute('data-edit-index'));
                openTeacherEdit(idx);
            });
        });

        var playBtns = previewEl.querySelectorAll('.ala-preview-play');
        playBtns.forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                var idx = parseInt(this.getAttribute('data-preview-index'));
                openTeacherPreview(idx);
            });
        });

        var items = previewEl.querySelectorAll('.ala-preview-clickable');
        items.forEach(function(item) {
            item.addEventListener('click', function() {
                var idx = parseInt(this.getAttribute('data-activity-index'));
                openTeacherPreview(idx);
            });
        });
    }

    function escapeAttr(str) {
        if (!str) return '';
        return str.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    // ========== TEACHER PREVIEW MODE ==========
    function openTeacherPreview(index) {
        isPreviewMode = true;
        currentIndex = index;
        selectedItem = null;
        progress = {};

        var manageSection = document.getElementById('ala-manage-section');
        var previewSection = document.getElementById('ala-teacher-preview');
        var labelEl = document.getElementById('ala-preview-label');

        if (manageSection) manageSection.style.display = 'none';
        if (previewSection) previewSection.style.display = 'block';

        var activity = activities[index];
        if (labelEl && activity) {
            var typeLabel = activity.type.replace('cardselect', 'Card Select')
                .replace('columnsort', 'Column Sort')
                .replace('categorysort', 'Category Sort')
                .replace('matching', 'Matching Pairs')
                .replace('ordering', 'Put in Order')
                .replace('flashcards', 'Flashcards')
                .replace('truefalseswipe', 'True or False')
                .replace('fillinblank', 'Fill in the Blank');
            labelEl.textContent = 'Preview: ' + typeLabel + ' - ' + (activity.title || '');
        }

        renderActivity(index);
    }

    function closeTeacherPreview() {
        isPreviewMode = false;

        var manageSection = document.getElementById('ala-manage-section');
        var previewSection = document.getElementById('ala-teacher-preview');
        var previewContent = document.getElementById('ala-preview-content');
        var previewFeedback = document.getElementById('ala-preview-feedback');

        if (previewSection) previewSection.style.display = 'none';
        if (manageSection) manageSection.style.display = 'block';
        if (previewContent) previewContent.innerHTML = '';
        if (previewFeedback) previewFeedback.classList.add('ala-feedback-hidden');
    }

    // ===================================================================
    // TEACHER: GENERATE ACTIVITIES
    // ===================================================================
    function renderAlaJobRoleChips() {
        var container = document.getElementById('ala-job-role-chips');
        if (!container) return;
        container.innerHTML = selectedAlaJobRoles.map(function(role, idx) {
            var safe = document.createElement('span');
            safe.textContent = role;
            return '<div class="ala-role-chip">' +
                '<span>' + safe.innerHTML + '</span>' +
                '<button type="button" class="ala-chip-remove" data-idx="' + idx + '">\u00d7</button>' +
                '</div>';
        }).join('');
        container.querySelectorAll('.ala-chip-remove').forEach(function(btn) {
            btn.addEventListener('click', function() {
                selectedAlaJobRoles.splice(parseInt(btn.getAttribute('data-idx'), 10), 1);
                renderAlaJobRoleChips();
            });
        });
        var input = document.getElementById('ala-job-role-input');
        if (input) input.disabled = selectedAlaJobRoles.length >= 5;
    }

    function initTeacherForm() {
        // Restore textarea content from sessionStorage if a regeneration reload just occurred
        var savedContent = sessionStorage.getItem('ala_content_' + CONFIG.cmid);
        if (savedContent) {
            var restoredEl = document.getElementById('ala-content');
            if (restoredEl) {
                restoredEl.value = savedContent;
            }
            sessionStorage.removeItem('ala_content_' + CONFIG.cmid);
        }

        // Fetch credits
        ajaxCall('getcredits', {}, function(result) {
            var creditsEl = document.getElementById('ala-credits-value');
            if (creditsEl && result.ok) {
                creditsEl.textContent = result.credits;
            }
        });

        // Update total cost when activity count or voiceover toggle changes
        var activityCountEl = document.getElementById('ala-activitycount');
        var totalCostEl = document.getElementById('ala-total-cost');
        var costPerActEl = document.getElementById('ala-cost-per-activity');
        var voiceoverToggleEl = document.getElementById('ala-voiceover-toggle');
        function updateAlaCost() {
            var count = activityCountEl ? parseInt(activityCountEl.value) || 5 : 5;
            var voOn = voiceoverToggleEl && voiceoverToggleEl.checked;
            var rate = voOn ? 3 : 2;
            if (costPerActEl) costPerActEl.textContent = rate + ' credits';
            if (totalCostEl) totalCostEl.textContent = (count * rate) + ' credits';
        }
        if (activityCountEl) {
            activityCountEl.addEventListener('change', updateAlaCost);
        }
        if (voiceoverToggleEl) {
            voiceoverToggleEl.addEventListener('change', updateAlaCost);
            var voiceoverFieldsEl = document.getElementById('ala-voiceover-fields');
            voiceoverToggleEl.addEventListener('change', function() {
                if (voiceoverFieldsEl) voiceoverFieldsEl.style.display = this.checked ? 'block' : 'none';
            });
        }

        // Job level pills  -  multi-select toggle.
        var pillContainer = document.getElementById('ala-job-level-pills');
        if (pillContainer) {
            pillContainer.addEventListener('click', function(e) {
                var pill = e.target.closest('.ala-level-pill');
                if (!pill) return;
                var val = pill.getAttribute('data-value');
                var idx = selectedAlaJobLevels.indexOf(val);
                if (idx > -1) {
                    selectedAlaJobLevels.splice(idx, 1);
                    pill.classList.remove('ala-level-active');
                } else {
                    selectedAlaJobLevels.push(val);
                    pill.classList.add('ala-level-active');
                }
            });
        }

        // Industry SELECT and sector SELECT population
        var alaIndustryEl = document.getElementById('ala-scenario-industry');
        var alaSectorEl = document.getElementById('ala-scenario-sector');
        if (alaIndustryEl) {
            populateIndustrySelect(alaIndustryEl);
            alaIndustryEl.addEventListener('change', function() {
                if (alaSectorEl) { populateSectorSelect(alaSectorEl, this.value); }
            });
        }

        // Job role chips  -  press Enter or comma to add, max 5.
        var roleInput = document.getElementById('ala-job-role-input');
        if (roleInput) {
            roleInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ',') {
                    e.preventDefault();
                    var val = roleInput.value.trim().replace(/,$/, '');
                    if (val && selectedAlaJobRoles.indexOf(val) === -1 && selectedAlaJobRoles.length < 5) {
                        selectedAlaJobRoles.push(val);
                        renderAlaJobRoleChips();
                    }
                    roleInput.value = '';
                }
            });
        }

        // Generate button
        var genBtn = document.getElementById('ala-generate-btn');
        if (genBtn) {
            genBtn.addEventListener('click', function() {
                var content = document.getElementById('ala-content').value.trim();
                if (!content) {
                    alert(CONFIG.strings.error_no_content);
                    return;
                }

                var language = document.getElementById('ala-language').value;
                var activitycount = parseInt(document.getElementById('ala-activitycount').value);

                // Show progress
                genBtn.disabled = true;
                genBtn.textContent = CONFIG.strings.generating;
                var progressBar = document.getElementById('ala-progress-bar');
                if (progressBar) progressBar.style.display = 'block';

                var fill = document.getElementById('ala-progress-fill');
                var progressText = document.getElementById('ala-progress-text');
                var pct = 0;
                var interval = setInterval(function() {
                    pct = Math.min(pct + Math.random() * 8, 90);
                    if (fill) fill.style.width = pct + '%';
                }, 500);

                var voiceoverOn = voiceoverToggleEl && voiceoverToggleEl.checked;
                var params = {
                    content: content,
                    language: language,
                    activitycount: activitycount
                };

                var scenarioToggle = document.getElementById('ala-scenario-toggle');
                if (scenarioToggle && scenarioToggle.checked) {
                    params.scenariomode = '1';
                    var countryEl = document.getElementById('ala-scenario-country');
                    var industryEl = document.getElementById('ala-scenario-industry');
                    if (countryEl && countryEl.value.trim()) params.scenariocountry = countryEl.value.trim();
                    if (industryEl && industryEl.value.trim()) params.scenarioindustry = industryEl.value.trim();
                    var sectorEl = document.getElementById('ala-scenario-sector');
                    if (sectorEl && sectorEl.value.trim()) params.scenariosector = sectorEl.value.trim();
                    if (selectedAlaJobLevels.length > 0) params.scenarioworkerlevel = selectedAlaJobLevels.join(', ');
                    if (selectedAlaJobRoles.length > 0) params.scenariojobroles = selectedAlaJobRoles.join(', ');
                }

                if (voiceoverOn) {
                    params.voiceoverenabled = '1';
                    var voiceGenderEl = document.getElementById('ala-voice-gender');
                    var voiceIdEl = document.getElementById('ala-voice-id');
                    params.voicegender = voiceGenderEl ? voiceGenderEl.value : 'female';
                    params.voiceid = voiceIdEl ? voiceIdEl.value : 'Zephyr';
                }

                /* v1.5.49 ASYNC: Start job (~500ms), then poll every 3s. Bypasses proxy timeout. */
                function handleGenError(msg) {
                    clearInterval(interval);
                    genBtn.disabled = false;
                    genBtn.textContent = 'Generate Activities';
                    if (progressBar) progressBar.style.display = 'none';
                    alert(msg || 'Generation failed');
                }

                function doPoll(jobId) {
                    ajaxCall('poll_job', {jobId: jobId}, function(pollResult) {
                        if (!pollResult || !pollResult.ok) {
                            handleGenError((pollResult && pollResult.error) || 'Poll failed');
                            return;
                        }
                        if (pollResult.status === 'done') {
                            clearInterval(interval);
                            if (fill) fill.style.width = '100%';
                            var result = pollResult.result;
                            if (result && result.ok && result.activities) {
                                // Save generated activities to the Moodle DB before reloading.
                                // The generate_async case starts the job and returns immediately
                                // without saving  -  so the save must happen here after polling.
                                var saveParams = {
                                    activitiesjson: JSON.stringify(result.activities),
                                    content: content,
                                    language: language,
                                    activitycount: activitycount
                                };
                                if (voiceoverOn) {
                                    saveParams.voiceoverenabled = '1';
                                    var vgEl = document.getElementById('ala-voice-gender');
                                    var viEl = document.getElementById('ala-voice-id');
                                    saveParams.voicegender = vgEl ? vgEl.value : 'female';
                                    saveParams.voiceid = viEl ? viEl.value : 'Zephyr';
                                }
                                ajaxCall('save_generated', saveParams, function(saveResult) {
                                    if (saveResult && saveResult.ok) {
                                        if (progressText) progressText.textContent = CONFIG.strings.generate_success;
                                        setTimeout(function() {
                                            var contentEl = document.getElementById('ala-content');
                                            if (contentEl) {
                                                sessionStorage.setItem('ala_content_' + CONFIG.cmid, contentEl.value);
                                            }
                                            var langEl = document.getElementById('ala-language');
                                            if (langEl) {
                                                sessionStorage.setItem('ala_language_' + CONFIG.cmid, langEl.value);
                                            }
                                            var countEl = document.getElementById('ala-activitycount');
                                            if (countEl) {
                                                sessionStorage.setItem('ala_activitycount_' + CONFIG.cmid, countEl.value);
                                            }
                                            var scenToggle = document.getElementById('ala-scenario-toggle');
                                            if (scenToggle) {
                                                sessionStorage.setItem('ala_scenariomode_' + CONFIG.cmid, scenToggle.checked ? '1' : '0');
                                                if (scenToggle.checked) {
                                                    var countryEl2 = document.getElementById('ala-scenario-country');
                                                    var industryEl2 = document.getElementById('ala-scenario-industry');
                                                    var sectorEl2 = document.getElementById('ala-scenario-sector');
                                                    if (countryEl2) sessionStorage.setItem('ala_country_' + CONFIG.cmid, countryEl2.value);
                                                    if (industryEl2) sessionStorage.setItem('ala_industry_' + CONFIG.cmid, industryEl2.value);
                                                    if (sectorEl2) sessionStorage.setItem('ala_sector_' + CONFIG.cmid, sectorEl2.value);
                                                }
                                            }
                                            window.location.reload();
                                        }, 1000);
                                    } else {
                                        handleGenError((saveResult && saveResult.error) || 'Failed to save activities');
                                    }
                                });
                            } else {
                                handleGenError((result && result.error) || 'Generation failed');
                            }
                            return;
                        }
                        if (pollResult.status === 'error') {
                            handleGenError(pollResult.error || 'Generation failed');
                            return;
                        }
                        /* pending / processing  -  keep polling */
                        setTimeout(function() { doPoll(jobId); }, 3000);
                    });
                }

                ajaxCall('generate_async', params, function(startResult) {
                    if (!startResult || !startResult.ok || !startResult.jobId) {
                        handleGenError((startResult && startResult.error) || 'Failed to start generation');
                        return;
                    }
                    setTimeout(function() { doPoll(startResult.jobId); }, 3000);
                });
            });
        }

        // Regenerate button  -  show form pre-populated with last-used settings
        var regenBtn = document.getElementById('ala-regenerate-btn');
        if (regenBtn) {
            regenBtn.addEventListener('click', function() {
                // Restore last-used settings from sessionStorage
                var savedContent = sessionStorage.getItem('ala_content_' + CONFIG.cmid);
                if (savedContent) {
                    var contentEl = document.getElementById('ala-content');
                    if (contentEl) contentEl.value = savedContent;
                }
                var savedLang = sessionStorage.getItem('ala_language_' + CONFIG.cmid);
                if (savedLang) {
                    var langEl = document.getElementById('ala-language');
                    if (langEl) langEl.value = savedLang;
                }
                var savedCount = sessionStorage.getItem('ala_activitycount_' + CONFIG.cmid);
                if (savedCount) {
                    var countEl = document.getElementById('ala-activitycount');
                    if (countEl) {
                        countEl.value = savedCount;
                        countEl.dispatchEvent(new Event('change'));
                    }
                }
                var savedScenario = sessionStorage.getItem('ala_scenariomode_' + CONFIG.cmid);
                if (savedScenario === '1') {
                    var scenToggle = document.getElementById('ala-scenario-toggle');
                    if (scenToggle && !scenToggle.checked) {
                        scenToggle.checked = true;
                        scenToggle.dispatchEvent(new Event('change'));
                    }
                    var savedCountry = sessionStorage.getItem('ala_country_' + CONFIG.cmid);
                    var savedIndustry = sessionStorage.getItem('ala_industry_' + CONFIG.cmid);
                    var savedSector = sessionStorage.getItem('ala_sector_' + CONFIG.cmid);
                    if (savedCountry) {
                        var countryEl = document.getElementById('ala-scenario-country');
                        if (countryEl) countryEl.value = savedCountry;
                    }
                    if (savedIndustry) {
                        var industryEl = document.getElementById('ala-scenario-industry');
                        if (industryEl) industryEl.value = savedIndustry;
                    }
                    if (savedSector) {
                        var sectorEl = document.getElementById('ala-scenario-sector');
                        if (sectorEl) sectorEl.value = savedSector;
                    }
                }
                var formSection = document.getElementById('ala-form-section');
                var manageSection = document.getElementById('ala-manage-section');
                if (formSection) formSection.style.display = 'block';
                if (manageSection) manageSection.style.display = 'none';
            });
        }

        // Regenerate Audio button  -  re-runs TTS for existing activities with updated voice settings
        var regenAudioBtn = document.getElementById('ala-regen-audio-btn');
        if (regenAudioBtn) {
            regenAudioBtn.addEventListener('click', function() {
                if (!CONFIG.activitiesjson) { alert('No activities to generate audio for.'); return; }
                var activitiesForAudio = typeof CONFIG.activitiesjson === 'string' ? JSON.parse(CONFIG.activitiesjson) : CONFIG.activitiesjson;
                var voicelang = (typeof CONFIG !== 'undefined' && CONFIG.voiceid) ? 'en-AU' : 'en-AU';
                var vid = (typeof CONFIG !== 'undefined' && CONFIG.voiceid) ? CONFIG.voiceid : 'Zephyr';
                regenAudioBtn.disabled = true;
                regenAudioBtn.textContent = 'Generating audio...';
                ajaxCall('regenerateaudio', {
                    activitiesjson: JSON.stringify(activitiesForAudio),
                    voicelanguage: voicelang,
                    voiceid: vid
                }, function(result) {
                    regenAudioBtn.disabled = false;
                    regenAudioBtn.textContent = 'Regenerate Audio';
                    if (result && result.ok && result.activities) {
                        alert('Audio regenerated successfully!');
                        location.reload();
                    } else {
                        alert('Audio regeneration failed: ' + ((result && result.error) || 'Unknown error'));
                    }
                });
            });
        }

        // Back button for preview mode
        var backBtn = document.getElementById('ala-preview-back-btn');
        if (backBtn) {
            backBtn.addEventListener('click', function() {
                closeTeacherPreview();
            });
        }

        // Back button for edit mode
        var editBackBtn = document.getElementById('ala-edit-back-btn');
        if (editBackBtn) {
            editBackBtn.addEventListener('click', function() {
                closeTeacherEdit();
            });
        }

        // Preview existing activities
        if (CONFIG.hasactivities && CONFIG.activitiesjson) {
            var previewEl = document.getElementById('ala-activities-preview');
            if (previewEl) {
                try {
                    var acts = typeof CONFIG.activitiesjson === 'string' ? JSON.parse(CONFIG.activitiesjson) : CONFIG.activitiesjson;
                    activities = acts;
                    var html = '';
                    acts.forEach(function(act, idx) {
                        var typeLabel = act.type.replace('cardselect', 'Card Select')
                            .replace('columnsort', 'Column Sort')
                            .replace('categorysort', 'Category Sort')
                            .replace('matching', 'Matching Pairs')
                            .replace('ordering', 'Put in Order')
                            .replace('flashcards', 'Flashcards')
                            .replace('truefalseswipe', 'True or False')
                            .replace('fillinblank', 'Fill in the Blank');
                        html += '<div class="ala-preview-item ala-preview-clickable" data-activity-index="' + idx + '">';
                        html += '<span class="ala-preview-num">' + (idx + 1) + '</span>';
                        html += '<span class="ala-preview-type">' + typeLabel + '</span>';
                        html += '<span class="ala-preview-title">' + escapeHtml(act.title) + '</span>';
                        html += '<span class="ala-preview-edit" data-edit-index="' + idx + '" title="Edit activity"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/></svg></span>';
                        html += '<span class="ala-preview-play" data-preview-index="' + idx + '"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="6 3 20 12 6 21 6 3"/></svg></span>';
                        html += '</div>';
                    });
                    previewEl.innerHTML = html;

                    bindPreviewItemClicks(previewEl);
                } catch(e) {
                    previewEl.innerHTML = '<p>Activities generated and ready for students.</p>';
                }
            }
        }
    }

    // ===================================================================
    // STUDENT: START PLAYER
    // ===================================================================
    function initStudentPlayer() {
        if (!CONFIG.activitiesjson) return;

        try {
            activities = typeof CONFIG.activitiesjson === 'string' ? JSON.parse(CONFIG.activitiesjson) : CONFIG.activitiesjson;
        } catch(e) {
            return;
        }

        if (!activities || activities.length === 0) return;

        // Bind review navigation buttons
        var reviewBtn = document.getElementById('ala-review-btn');
        if (reviewBtn) {
            reviewBtn.addEventListener('click', function() {
                enterReviewMode();
            });
        }
        var reviewPrevBtn = document.getElementById('ala-review-prev');
        if (reviewPrevBtn) {
            reviewPrevBtn.addEventListener('click', function() {
                reviewPrev();
            });
        }
        var reviewNextBtn = document.getElementById('ala-review-next');
        if (reviewNextBtn) {
            reviewNextBtn.addEventListener('click', function() {
                reviewNext();
            });
        }
        var reviewDoneBtn = document.getElementById('ala-review-done');
        if (reviewDoneBtn) {
            reviewDoneBtn.addEventListener('click', function() {
                exitReviewMode();
            });
        }
        var practiceAgainBtn = document.getElementById('ala-practice-again-btn');
        if (practiceAgainBtn) {
            practiceAgainBtn.addEventListener('click', function() {
                practiceAgain();
            });
        }

        var etaContainer = document.getElementById('ala-player-eta');
        if (etaContainer) {
            etaContainer.innerHTML = buildEtaBannerHtml(activities);
        }

        ajaxCall('startattempt', {}, function(result) {
            if (!result.ok) return;

            attemptId = result.attemptid;

            if (result.completed) {
                var playerContent = document.getElementById('ala-player-content');
                var completeScreen = document.getElementById('ala-player-complete');
                var headerEl = document.getElementById('ala-player-header');
                if (playerContent) playerContent.style.display = 'none';
                if (headerEl) headerEl.style.display = 'none';
                if (completeScreen) completeScreen.style.display = 'flex';

                var fillEl = document.getElementById('ala-player-progress-fill');
                if (fillEl) fillEl.style.width = '100%';

                var continueBtn = document.getElementById('ala-continue-btn');
                if (continueBtn && ALA_CONFIG.nextactivityurl) {
                    continueBtn.href = ALA_CONFIG.nextactivityurl;
                    continueBtn.style.display = 'inline-flex';
                }
                return;
            }

            lockMoodleNav();

            progress = result.progress || {};
            currentIndex = result.currentactivity || 0;

            while (currentIndex < activities.length && progress[currentIndex] === true) {
                currentIndex++;
            }

            if (currentIndex >= activities.length) {
                completeAllActivities();
            } else {
                renderActivity(currentIndex);
            }
        });
    }

    // ===================================================================
    // INIT
    // ===================================================================
    return {
        init: function() {
            CONFIG = window.ALA_CONFIG;
            if (!CONFIG) return;

            // v1.6.8 FIX-TOUCH-DETECT: navigator.maxTouchPoints > 0 is true on Windows
            // Chrome/Edge even on pure desktop machines (no touchscreen), causing drag-and-
            // drop to be wrongly disabled for all ordering/sorting activities. Removed that
            // check; now only use ontouchstart + pointer:coarse which reliably distinguish
            // genuine touch-primary devices from mouse-primary laptops.
            isTouchDevice = (('ontouchstart' in window) ||
                (window.matchMedia && window.matchMedia('(pointer: coarse)').matches));

            if (CONFIG.cancreate) {
                initTeacherForm();
            } else if (CONFIG.hasactivities) {
                initStudentPlayer();
            }
        }
    };
});
