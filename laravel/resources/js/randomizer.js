function randomInt(max) {
    return Math.floor(Math.random() * max);
}

function randomElement(list) {
    return list[randomInt(list.length)];
}

function pickUniqueIndexes(count, poolSize) {
    const picked = [];
    while (picked.length < count) {
        const index = randomInt(poolSize);
        if (!picked.includes(index)) {
            picked.push(index);
        }
    }
    return picked;
}

function randomAssistLetter() {
    return ['A', 'B', 'C'][randomInt(3)];
}

function randomAssistGreek() {
    return ['α', 'β', 'γ'][randomInt(3)];
}

function randomButton() {
    return ['LP', 'HP', 'A1', 'LK', 'HK', 'A2'][randomInt(6)];
}

/* === Dragon Ball FighterZ === */
function initDbfzRandomizer() {
    const button = document.getElementById('dbfz-new-team');
    if (! button) return;

    const characters = [
        { name: 'Master Roshi', image: 'https://www.dustloop.com/wiki/images/thumb/8/8d/DBFZ_Master_Roshi_Icon.png/98px-DBFZ_Master_Roshi_Icon.png' },
        { name: 'SSB Gogeta', image: 'https://www.dustloop.com/wiki/images/thumb/1/1a/DBFZ_SSB_Gogeta_Icon.png/98px-DBFZ_SSB_Gogeta_Icon.png' },
        { name: 'Goku', image: 'https://www.dustloop.com/wiki/images/thumb/b/bb/DBFZ_Goku_Icon.png/98px-DBFZ_Goku_Icon.png' },
        { name: 'Tien', image: 'https://www.dustloop.com/wiki/images/thumb/8/85/DBFZ_Tien_Icon.png/98px-DBFZ_Tien_Icon.png' },
        { name: 'Yamcha', image: 'https://www.dustloop.com/wiki/images/thumb/0/08/DBFZ_Yamcha_Icon.png/98px-DBFZ_Yamcha_Icon.png' },
        { name: 'Krillin', image: 'https://www.dustloop.com/wiki/images/thumb/5/51/DBFZ_Krillin_Icon.png/98px-DBFZ_Krillin_Icon.png' },
        { name: 'SS Goku', image: 'https://www.dustloop.com/wiki/images/thumb/8/87/DBFZ_SS_Goku_Icon.png/98px-DBFZ_SS_Goku_Icon.png' },
        { name: 'Frieza', image: 'https://www.dustloop.com/wiki/images/thumb/a/a0/DBFZ_Frieza_Icon.png/98px-DBFZ_Frieza_Icon.png' },
        { name: 'Kid Buu', image: 'https://www.dustloop.com/wiki/images/thumb/a/a4/DBFZ_Kid_Buu_Icon.png/98px-DBFZ_Kid_Buu_Icon.png' },
        { name: 'Captain Ginyu', image: 'https://www.dustloop.com/wiki/images/thumb/5/59/DBFZ_Captain_Ginyu_Icon.png/98px-DBFZ_Captain_Ginyu_Icon.png' },
        { name: 'Nappa', image: 'https://www.dustloop.com/wiki/images/thumb/0/00/DBFZ_Nappa_Icon.png/98px-DBFZ_Nappa_Icon.png' },
        { name: 'Vegeta', image: 'https://www.dustloop.com/wiki/images/thumb/6/66/DBFZ_Vegeta_Icon.png/98px-DBFZ_Vegeta_Icon.png' },
        { name: 'DBS Broly', image: 'https://www.dustloop.com/wiki/images/thumb/0/0e/DBFZ_DBS_Broly_Icon.png/98px-DBFZ_DBS_Broly_Icon.png' },
        { name: 'Super Baby 2', image: 'https://www.dustloop.com/wiki/images/thumb/4/4e/DBFZ_Super_Baby_2_Icon.png/98px-DBFZ_Super_Baby_2_Icon.png' },
        { name: 'GT Goku', image: 'https://www.dustloop.com/wiki/images/thumb/0/01/DBFZ_GT_Goku_Icon.png/98px-DBFZ_GT_Goku_Icon.png' },
        { name: 'Android 17', image: 'https://www.dustloop.com/wiki/images/thumb/4/40/DBFZ_Android_17_Icon.png/98px-DBFZ_Android_17_Icon.png' },
        { name: 'Bardock', image: 'https://www.dustloop.com/wiki/images/thumb/3/31/DBFZ_Bardock_Icon.png/98px-DBFZ_Bardock_Icon.png' },
        { name: 'SSB Goku', image: 'https://www.dustloop.com/wiki/images/thumb/9/94/DBFZ_SSB_Goku_Icon.png/98px-DBFZ_SSB_Goku_Icon.png' },
        { name: 'Adult Gohan', image: 'https://www.dustloop.com/wiki/images/thumb/1/14/DBFZ_Adult_Gohan_Icon.png/98px-DBFZ_Adult_Gohan_Icon.png' },
        { name: 'Trunks', image: 'https://www.dustloop.com/wiki/images/thumb/b/b8/DBFZ_Trunks_Icon.png/98px-DBFZ_Trunks_Icon.png' },
        { name: 'SS Vegeta', image: 'https://www.dustloop.com/wiki/images/thumb/8/8d/DBFZ_SS_Vegeta_Icon.png/98px-DBFZ_SS_Vegeta_Icon.png' },
        { name: 'Cell', image: 'https://www.dustloop.com/wiki/images/thumb/7/75/DBFZ_Cell_Icon.png/98px-DBFZ_Cell_Icon.png' },
        { name: 'Android 18', image: 'https://www.dustloop.com/wiki/images/thumb/a/ac/DBFZ_Android_18_Icon.png/98px-DBFZ_Android_18_Icon.png' },
        { name: 'Android 16', image: 'https://www.dustloop.com/wiki/images/thumb/6/6e/DBFZ_Android_16_Icon.png/98px-DBFZ_Android_16_Icon.png' },
        { name: 'Android 21', image: 'https://www.dustloop.com/wiki/images/thumb/5/5f/DBFZ_Android_21_Icon.png/98px-DBFZ_Android_21_Icon.png' },
        { name: 'Broly', image: 'https://www.dustloop.com/wiki/images/thumb/8/8f/DBFZ_Broly_Icon.png/98px-DBFZ_Broly_Icon.png' },
        { name: 'Cooler', image: 'https://www.dustloop.com/wiki/images/thumb/b/b3/DBFZ_Cooler_Icon.png/98px-DBFZ_Cooler_Icon.png' },
        { name: 'Janemba', image: 'https://www.dustloop.com/wiki/images/thumb/a/a3/DBFZ_Janemba_Icon.png/98px-DBFZ_Janemba_Icon.png' },
        { name: 'UI Goku', image: 'https://www.dustloop.com/wiki/images/thumb/9/90/DBFZ_UI_Goku_Icon.png/98px-DBFZ_UI_Goku_Icon.png' },
        { name: 'Videl', image: 'https://www.dustloop.com/wiki/images/thumb/0/0d/DBFZ_Videl_Icon.png/98px-DBFZ_Videl_Icon.png' },
        { name: 'SSB Vegito', image: 'https://www.dustloop.com/wiki/images/thumb/8/89/DBFZ_SSB_Vegito_Icon.png/98px-DBFZ_SSB_Vegito_Icon.png' },
        { name: 'SSB Vegeta', image: 'https://www.dustloop.com/wiki/images/thumb/9/92/DBFZ_SSB_Vegeta_Icon.png/98px-DBFZ_SSB_Vegeta_Icon.png' },
        { name: 'Gotenks', image: 'https://www.dustloop.com/wiki/images/thumb/f/f2/DBFZ_Gotenks_Icon.png/98px-DBFZ_Gotenks_Icon.png' },
        { name: 'Piccolo', image: 'https://www.dustloop.com/wiki/images/thumb/c/c8/DBFZ_Piccolo_Icon.png/98px-DBFZ_Piccolo_Icon.png' },
        { name: 'Teen Gohan', image: 'https://www.dustloop.com/wiki/images/thumb/2/2b/DBFZ_Teen_Gohan_Icon.png/98px-DBFZ_Teen_Gohan_Icon.png' },
        { name: 'SS4 Gogeta', image: 'https://www.dustloop.com/wiki/images/thumb/f/fa/DBFZ_SS4_Gogeta_Icon.png/98px-DBFZ_SS4_Gogeta_Icon.png' },
        { name: 'Majin Buu', image: 'https://www.dustloop.com/wiki/images/thumb/d/d0/DBFZ_Majin_Buu_Icon.png/98px-DBFZ_Majin_Buu_Icon.png' },
        { name: 'Beerus', image: 'https://www.dustloop.com/wiki/images/thumb/4/42/DBFZ_Beerus_Icon.png/98px-DBFZ_Beerus_Icon.png' },
        { name: 'Hit', image: 'https://www.dustloop.com/wiki/images/thumb/d/dd/DBFZ_Hit_Icon.png/98px-DBFZ_Hit_Icon.png' },
        { name: 'Goku Black', image: 'https://www.dustloop.com/wiki/images/thumb/a/a3/DBFZ_Goku_Black_Icon.png/98px-DBFZ_Goku_Black_Icon.png' },
        { name: 'Fused Zamasu', image: 'https://www.dustloop.com/wiki/images/thumb/1/17/DBFZ_Fused_Zamasu_Icon.png/98px-DBFZ_Fused_Zamasu_Icon.png' },
        { name: 'Jiren', image: 'https://www.dustloop.com/wiki/images/thumb/c/c7/DBFZ_Jiren_Icon.png/98px-DBFZ_Jiren_Icon.png' },
        { name: 'Kefla', image: 'https://www.dustloop.com/wiki/images/thumb/5/5d/DBFZ_Kefla_Icon.png/98px-DBFZ_Kefla_Icon.png' },
    ];

    function generateTeam() {
        const picks = pickUniqueIndexes(3, characters.length);

        picks.forEach((charIndex, slot) => {
            const character = characters[charIndex];
            document.getElementById(`dbfz-name-${slot}`).textContent = `${character.name} (${randomAssistLetter()})`;
            document.getElementById(`dbfz-portrait-${slot}`).src = character.image;
            document.getElementById(`dbfz-color-${slot}`).textContent = `Color ${randomInt(25)}`;
        });
    }

    button.addEventListener('click', generateTeam);
    generateTeam();
}

/* === Marvel vs Capcom 2 === */
function initMvc2Randomizer() {
    const newTeamButton = document.getElementById('mvc2-new-team');
    const ratioTeamButton = document.getElementById('mvc2-ratio-team');
    if (! newTeamButton || ! ratioTeamButton) return;

    const ratioInput = document.getElementById('mvc2-ratio-max');
    const titleEl = document.getElementById('mvc2-title');

    const characters = [
        { name: 'Sentinel', ratio: 5, image: 'https://srk.shib.live/images/0/00/Portrait_MVC2_Sentinel.png' },
        { name: 'Magneto', ratio: 5, image: 'https://srk.shib.live/images/f/fa/Portrait_MVC2_Magneto.png' },
        { name: 'Storm', ratio: 5, image: 'https://srk.shib.live/images/3/31/Portrait_MVC2_Storm.png' },
        { name: 'Cable', ratio: 5, image: 'https://srk.shib.live/images/0/0b/Portrait_MVC2_Cable.png' },
        { name: 'Iron Man', ratio: 5, image: 'https://srk.shib.live/images/9/9b/Portrait_MVC2_Iron_Man.png' },
        { name: 'Spiral', ratio: 5, image: 'https://srk.shib.live/images/6/6e/Portrait_MVC2_Spiral.png' },
        { name: 'Strider', ratio: 4, image: 'https://srk.shib.live/images/a/a6/Portrait_MVC2_Strider.png' },
        { name: 'War Machine', ratio: 4, image: 'https://srk.shib.live/images/8/89/Portrait_MVC2_War_Machine.png' },
        { name: 'Doctor Doom', ratio: 4, image: 'https://srk.shib.live/images/7/72/Portrait_MVC2_DrDoom.png' },
        { name: 'Cyclops', ratio: 4, image: 'https://srk.shib.live/images/a/a9/Portrait_MVC2_Cyclops.png' },
        { name: 'Captain Commando', ratio: 4, image: 'https://srk.shib.live/images/7/77/Portrait_MVC2_CaptCommando.png' },
        { name: 'Psylocke', ratio: 4, image: 'https://srk.shib.live/images/3/35/Portrait_MVC2_Psylocke.png' },
        { name: 'Cammy', ratio: 4, image: 'https://srk.shib.live/images/9/97/Portrait_MVC2_Cammy.png' },
        { name: 'Dhalsim', ratio: 4, image: 'https://srk.shib.live/images/c/cc/Portrait_MVC2_Dhalsim.png' },
        { name: 'Blackheart', ratio: 4, image: 'https://srk.shib.live/images/1/16/Portrait_MVC2_Blackheart.png' },
        { name: 'Tron Bonne', ratio: 4, image: 'https://srk.shib.live/images/c/cc/Portrait_MVC2_Tron_Bonne.png' },
        { name: 'Ruby Heart', ratio: 3, image: 'https://srk.shib.live/images/f/f2/Portrait_MVC2_Ruby_Heart.png' },
        { name: 'Colossus', ratio: 3, image: 'https://srk.shib.live/images/0/0a/Portrait_MVC2_Colossus.png' },
        { name: 'Omega Red', ratio: 3, image: 'https://srk.shib.live/images/c/c8/Portrait_MVC2_Omega_Red.png' },
        { name: 'Rogue', ratio: 3, image: 'https://srk.shib.live/images/1/17/Portrait_MVC2_Rogue.png' },
        { name: 'Silver Samurai', ratio: 3, image: 'https://srk.shib.live/images/1/19/Portrait_MVC2_Silver_Samurai.png' },
        { name: 'Iceman', ratio: 3, image: 'https://srk.shib.live/images/0/06/Portrait_MVC2_Iceman.png' },
        { name: 'Jill', ratio: 3, image: 'https://srk.shib.live/images/b/b4/Portrait_MVC2_Jill_Valentine.png' },
        { name: 'Mega Man', ratio: 3, image: 'https://srk.shib.live/images/5/56/Portrait_MVC2_Mega_Man.png' },
        { name: 'Guile', ratio: 3, image: 'https://srk.shib.live/images/e/ed/Portrait_MVC2_Guile.png' },
        { name: 'Juggernaut', ratio: 3, image: 'https://srk.shib.live/images/7/79/Portrait_MVC2_Juggernaut.png' },
        { name: 'Gambit', ratio: 2, image: 'https://srk.shib.live/images/5/59/Portrait_MVC2_Gambit.png' },
        { name: 'Ken', ratio: 2, image: 'https://srk.shib.live/images/d/d4/Portrait_MVC2_Ken.png' },
        { name: 'Akuma', ratio: 2, image: 'https://srk.shib.live/images/4/41/Portrait_MVC2_Akuma.png' },
        { name: 'Ryu', ratio: 2, image: 'https://srk.shib.live/images/e/e5/Portrait_MVC2_Ryu.png' },
        { name: 'Anakaris', ratio: 2, image: 'https://srk.shib.live/images/3/33/Portrait_MVC2_Anakaris.png' },
        { name: 'Sonson', ratio: 2, image: 'https://srk.shib.live/images/7/75/Portrait_MVC2_Sonson.png' },
        { name: 'Zangief', ratio: 2, image: 'https://srk.shib.live/images/0/04/Portrait_MVC2_Zangief.png' },
        { name: 'Morrigan', ratio: 2, image: 'https://srk.shib.live/images/0/08/Portrait_MVC2_Morrigan.png' },
        { name: 'Sabretooth', ratio: 2, image: 'https://srk.shib.live/images/e/e2/Portrait_MVC2_Sabertooth.png' },
        { name: 'Spiderman', ratio: 2, image: 'https://srk.shib.live/images/4/44/Portrait_MVC2_Spider-Man.png' },
        { name: 'Hulk', ratio: 2, image: 'https://srk.shib.live/images/7/72/Portrait_MVC2_Hulk.png' },
        { name: 'Charlie', ratio: 2, image: 'https://srk.shib.live/images/b/b7/Portrait_MVC2_Charlie.png' },
        { name: 'Sakura', ratio: 2, image: 'https://srk.shib.live/images/b/bd/Portrait_MVC2_Sakura.png' },
        { name: 'Marrow', ratio: 2, image: 'https://srk.shib.live/images/9/9b/Portrait_MVC2_Marrow.png' },
        { name: 'Venom', ratio: 2, image: 'https://srk.shib.live/images/4/45/Portrait_MVC2_Venom.png' },
        { name: 'Felicia', ratio: 2, image: 'https://srk.shib.live/images/2/2a/Portrait_MVC2_Felicia.png' },
        { name: 'B.B. Hood', ratio: 2, image: 'https://srk.shib.live/images/c/cb/Portrait_MVC2_BBHood.png' },
        { name: 'Jin', ratio: 2, image: 'https://srk.shib.live/images/2/24/Portrait_MVC2_Jin_Saotome.png' },
        { name: 'M. Bison', ratio: 2, image: 'https://srk.shib.live/images/b/bc/Portrait_MVC2_M._Bison.png' },
        { name: 'Thanos', ratio: 2, image: 'https://srk.shib.live/images/a/a0/Portrait_MVC2_Thanos.png' },
        { name: 'Dan', ratio: 1, image: 'https://srk.shib.live/images/7/7e/Portrait_MVC2_Dan.png' },
        { name: 'Kobun/Servbot', ratio: 1, image: 'https://srk.shib.live/images/3/39/Portrait_MVC2_Servbot.png' },
        { name: 'Hayato', ratio: 1, image: 'https://srk.shib.live/images/a/a2/Portrait_MVC2_Hayato.png' },
        { name: 'Amingo', ratio: 1, image: 'https://srk.shib.live/images/9/90/Portrait_MVC2_Amingo.png' },
        { name: 'Shuma-Gorath', ratio: 1, image: 'https://srk.shib.live/images/d/d5/Portrait_MVC2_Shuma-Gorath.png' },
        { name: 'Captain America', ratio: 1, image: 'https://srk.shib.live/images/2/2f/Portrait_MVC2_CaptAmerica.png' },
        { name: 'Chun-Li', ratio: 1, image: 'https://srk.shib.live/images/f/f8/Portrait_MVC2_Chun-Li.png' },
        { name: 'Wolverine - Claw', ratio: 1, image: 'https://srk.shib.live/images/9/98/Portrait_MVC2_Wolverine_Adamantium.png' },
        { name: 'Wolverine - Bone', ratio: 1, image: 'https://srk.shib.live/images/2/2e/Portrait_MVC2_Wolverine_Bone.png' },
        { name: 'Roll', ratio: 0, image: 'https://srk.shib.live/images/e/e4/Portrait_MVC2_Roll.png' },
    ];

    function showTeam(picks, title) {
        titleEl.textContent = title;

        const ratios = picks.map((charIndex) => characters[charIndex].ratio);
        document.getElementById('mvc2-ratios').textContent =
            `${ratios.join(' + ')} = ${ratios.reduce((sum, ratio) => sum + ratio, 0)}`;

        picks.forEach((charIndex, slot) => {
            const character = characters[charIndex];
            document.getElementById(`mvc2-name-${slot}`).textContent = `${character.name} (${randomAssistGreek()})`;
            document.getElementById(`mvc2-portrait-${slot}`).src = character.image;
            document.getElementById(`mvc2-color-${slot}`).textContent = randomButton();
        });
    }

    newTeamButton.addEventListener('click', function () {
        showTeam(pickUniqueIndexes(3, characters.length), 'New Team');
    });

    ratioTeamButton.addEventListener('click', function () {
        let remainingRatio = Number(ratioInput.value);
        const picks = [];
        let tries = 0;

        while (picks.length < 3 && tries < 1000) {
            tries++;
            const candidate = randomInt(characters.length);
            if (picks.includes(candidate)) continue;
            if (characters[candidate].ratio > remainingRatio) continue;

            picks.push(candidate);
            remainingRatio -= characters[candidate].ratio;
        }

        if (picks.length < 3) return;

        showTeam(picks, 'Ratio Team');
    });

    newTeamButton.click();
}

/* === Skullgirls === */
function initSkullgirlsRandomizer() {
    const button = document.getElementById('skullgirls-new-team');
    if (! button) return;

    const messageEl = document.getElementById('skullgirls-message');

    const characters = [
        { id: 0, name: 'Robo-Fortune', checkboxId: 'skullgirls-RoboFortune', colorCount: 31, moves: ['s.LP - Catcher Tongue', 's.MP - Flex Capacitor', 's.HP - Collimating Saw', 's.LK - HF Quartz Blade', 's.MK - Overclawk', 's.HK - Device Driver', 'c.LP - Hearing Blade', 'c.MP - Gain Medium', 'c.HP - Grounding Pound', 'c.LK - LF Quartz Blade', 'c.MK - Scroll Heel', 'c.HK - Launch Headrone', 'Theonite Beam (L) (236LP)', 'Theonite Beam (M) (236MP)', 'Theonite Beam (H) (236HP)', 'Headrone RAM (214LK)', 'Headrone Impact (214MK)', 'Headrone Salvo (214HK)', 'Danger! (L) (236LK)', 'Danger! (M) (236MK)', 'Danger! (H) (236HK)', 'Blast Processor (Throw)', 'Blast Processor (Back Throw)', 'Dash (PP)', 'Backdash (4PP)', 'Really Talks! (Taunt) (Start)'] },
        { id: 1, name: 'Valentine', checkboxId: 'skullgirls-Valentine', colorCount: 30, moves: ['s.LP - Check-Up', 's.MP - Transfemoral Amputation', 's.HP - Thoracotomy', 's.LK - Shin Splint', 's.MK - Chishibuki Juuji', 's.HK - IV Naginata', 'c.LP - Knee-jerk Hammer', 'c.MP - Venesection', 'c.HP - Skyward Strike', 'c.LK - Gedan Juuji', 'c.MK - Kakushi Caliper', 'c.HK - Kiri Barai', 'Dead Cross (L) (236LP)', 'Dead Cross (M) (236MP)', 'Dead Cross (H) (236HP)', 'Vial Hazard: Type A (214LP)', 'Vial Hazard: Type B (214MP)', 'Vial Hazard: Type C (214HP)', 'Savage Bypass (L) (236LK)', 'Savage Bypass (M) (236MK)', 'Savage Bypass (H) (236HK)', 'Mortuary Drop (214LK+LP)', 'Anesthesia (Throw)', 'Anesthesia (Back Throw)', 'Dash (PP)', 'Backdash (4PP)', 'Chocoglycemia (Taunt) (Start)'] },
        { id: 2, name: 'Cerebella', checkboxId: 'skullgirls-Cerebella', colorCount: 28, moves: ['s.LP - Tune Up', 's.MP - Medici Shakedown', 's.HP - Tent Stake Hammer', 's.LK - Point Cut', 's.MK - Cugine Kick', 's.HK - Adagio Swing', 'c.LP - Kneecapper', 'c.MP - Enforcer Elbow', 'c.HP - Boost-iere', 'c.LK - Diamond Scratch', 'c.MK - Loop de Loop', 'c.HK - Medici Legbreaker', 's.F + HP - Titan Knuckle', "Lock'n'Load (L) (236LP)", "Lock'n'Load (M) (236MP)", "Lock'n'Load (H) (236HP)", 'Run Stop (46LP+LK)', 'Kanchou (46MK+MP)', 'Battle Butt (46HK+HP)', 'Tumbling Run (46K)', 'Diamond Deflector (623LP)', 'Devil Horns (623MP)', 'Cerecopter (623HP)', 'Merry Go-Rilla (214LK+LP)', 'Diamond Drop (236LK+LP)', 'Excellebella (623LK+LP)', 'Cere-rana (Throw)', 'Cere-rana (Back Throw)', 'Dash (PP)', 'Backdash (4PP)', 'Medici Muscle (Taunt) (Start)'] },
        { id: 3, name: 'Ms Fortune', checkboxId: 'skullgirls-MsFortune', colorCount: 28, moves: ['s.LP - Neko Pun-ch', 's.MP - Facepalm', 's.HP - Ears Pierced', 's.LK - Flip Flop', 's.MK - One-Two Punisher', 's.HK - Wheel of Fortune', 'c.LP - Toy Touch', 'c.MP - Hand In Hand', 'c.HP - High Brow', 'c.LK - Knees and Toes', 'c.MK - Nail Clipper', "c.HK - Kitt n' Spin", 'Cat Scratch (L) (236LP)', 'Cat Scratch (M) (236MP)', 'Cat Scratch (H) (236HP)', 'El Gato (214K)', 'Cat Slide (236K)', 'Be Headed (214P)', 'Fiber Upper (L) (623LK)', 'Fiber Upper (M) (623MK)', 'Fiber Upper (H) (623HK)', 'Headbutt (2/4/5/6HP)', 'Zoom! (1/3HP)', 'Apotemnophobia (Throw)', 'Apotemnophobia (Back Throw)', 'Dash (PP)', 'Backdash (4PP)', 'Nyaaawn (Taunt) (Start)'] },
        { id: 4, name: 'Painwheel', checkboxId: 'skullgirls-Painwheel', colorCount: 28, moves: ['s.LP - Enmity Nail', 's.MP - Revulsion Shank', 's.HP - Fury Sledge', 's.LK - Puncture', 's.MK - Warp Spasm', 's.HK - Fracture', 'c.LP - Lacerate', 'c.MP - Cruel Lily', 'c.HP - Animosity Barbs', 'c.LK - Pierce', 'c.MK - Disfigure', 'c.HK - Malice Clover', 'f.HP - Ratchet Poppy (6HP)', 'Flight (214K)', 'Gae Bolga Stinger (L) (236LP)', 'Gae Bolga Stinger (M) (236MP)', 'Gae Bolga Stinger (H) (236HP)', 'Buer Reaper (L) (236LK)', 'Buer Reaper (M) (236MK)', 'Buer Reaper (H) (236HK)', 'Pinion Dash (L) (22LK)', 'Pinion Dash (M) (22MK)', 'Pinion Dash (H) (22HK)', 'Vice Crush / Hatred Piston (Throw)', 'Vice Crush / Hatred Piston (Back Throw)', 'Dash (PP)', 'Backdash (4PP)', 'SMILE (Taunt) (Start)'] },
        { id: 5, name: 'Squigly', checkboxId: 'skullgirls-Squigly', colorCount: 31, moves: ['s.LP - Lich Slap', 's.MP - Piercing Gaze', 's.HP - Bune Knuckle', 's.LK - Croisé', 's.MK - Death Drop', 's.HK - Dust to Dust', 'c.LP - Tail Light', 'c.MP - Serpent Spear', 'c.HP - Cremation', 'c.LK - Écart', 'c.MK - En Pointe', 'c.HK - Dance of Salome', 'f.HP - Ashes to Ashes (6HP)', 'Liver Mortis (236LP)', 'Center Stage (236MP)', "Drag 'n' Bite (236HP)", 'Draugen Punch (623P)', 'Arpeggio (236LK)', 'The Silver Chord (236MK)', 'Tremolo (236HK)', 'Pass Away (Throw)', 'Pass Away (Back Throw)', 'Dash (PP)', 'Backdash (4PP)', 'Snake Charmer (Taunt) (Start)'] },
        { id: 6, name: 'Filia', checkboxId: 'skullgirls-Filia', colorCount: 29, moves: ['s.LP - Snip Snip', 's.MP - Thinning Shears', 's.HP - Chompadour', 's.LK - Knee High', 's.MK - Leg Warmer', 's.HK - Samson Boot', 'c.LP - Comb Under', 'c.MP - Ariel Rave', 'c.HP - Queue Sting', 'c.LK - Ankle Sock', 'c.MK - French Twist', 'c.HK - Tread of Hair', 'Ringlet Spike (L) (236LP)', 'Ringlet Spike (M) (236MP)', 'Ringlet Spike (H) (236HP)', 'Ringlet Psych (623K)', 'Updo (L) (623LP)', 'Updo (M) (236MP)', 'Updo (H) (236HP)', 'Hairball (L) (214LK)', 'Hairball (M) (214MK)', 'Hairball (H) (214HK)', 'Samson Cuddle (Throw)', 'Samson Cuddle (Back Throw)', 'Dash (PP)', 'Backdash (4PP)', 'Born With It (Taunt) (Start)'] },
        { id: 7, name: 'Peacock', checkboxId: 'skullgirls-Peacock', colorCount: 28, moves: ['s.LP - Poke!', 's.MP - Pie Splat', 's.HP - Screwball Cannonball', 's.LK - Pop Eye', 's.MK - Springboard Panic', 's.HK - Kick the Football, Peacock', "c.LP - Sruff n'Puff", 'c.MP - Eyes of Tomorrow', 'c.HP - Red Hot Buckshot', 'c.LK - Curb Your Shoe', 'c.MK - Ant Wasted', 'c.HK - Banjo Trouble', 'Bang! (L) (236LP)', 'BANG! (M) (236MP)', 'Bang, bang, bang! (H) (236HP)', "George's Day Out (236LK)", 'Boxcar George (236MK)', 'George at the Air Show (236HK)', 'Shadow of Impending Doom (L) (214LP)', 'Shadow of Impending Doom (M) (214MP)', 'Shadow of Impending Doom (H) (214HP)', 'Shadow of Impending Doom (L) (214LP+MP)', 'Shadow of Impending Doom (M) (214MP+HP)', 'Shadow of Impending Doom (H) (214LP+HP)', 'The Hole Idea (L) (214LK)', 'The Hole Idea (M) (214MK)', 'The Hole Idea (H) (214HK)', 'Burlap Beatdown (Throw)', 'Burlap Beatdown (Back Throw)', 'Dash (PP)', 'Backdash (4PP)', 'Hi Hi Birdie (Taunt) (Start)'] },
        { id: 8, name: 'Parasoul', checkboxId: 'skullgirls-Parasoul', colorCount: 28, moves: ["s.LP - Touché", "s.MP - Coup d'arrêt", 's.HP - Arc de Feu', 's.LK - Persistence', "s.MK - Queen's Gambit", 's.HK - Elegance', 'c.LP - Garde', 'c.MP - Coup Double', 'c.HP - Prominence', 'c.LK - Virtue', 'c.MK - Beauty', 'c.HK - Honesty', 's.F + LP - Pistol Whip (6LP)', 's.F + MP - Coulé (6MP)', 's.F + HP - Lunge (6HP)', 's.B + HK - Forbearance (4HK)', 'Napalm Toss (L) (214LK)', 'Napalm Toss (M) (214MK)', 'Napalm Toss (H) (214HK)', 'Napalm Shot (L) (46LP)', 'Napalm Shot (M) (46MP)', 'Napalm Shot (H) (46HP)', 'Napalm Trigger (28LK)', 'Napalm Quake (28MK)', 'Napalm Pillar (28HK)', 'Egret Call (46LK)', 'Egret Dive (46MK)', 'Egret Charge (46HK)', 'Napalm Trap (Throw)', 'Napalm Trap (Back Throw)', 'Dash (PP)', 'Backdash (4PP)', 'Bâillement (Taunt) (Start)'] },
        { id: 9, name: 'Eliza', checkboxId: 'skullgirls-Eliza', colorCount: 32, moves: ['s.LP - Wadjab', 's.MP - Siren Serpopards', 's.HP - Sirocco Storm', 's.LK - Sandal Wedge', 's.MK - Chaos Banish', 's.HK - Solar Arc', 'c.LP - Nemes Set', 'c.MP - Middle of the Sphynx', 'c.HP - Isis Wings', "c.LK - Bast's Cuff", 'c.MK - Sobek Slide', 'c.HK - Solar Barge', 'Upper Khat (L) (623LP)', 'Upper Khat (M) (623MP)', 'Upper Khat (H) (623HP)', 'Osiris Spiral (L) (214LK)', 'Osiris Spiral (M) (214MK)', 'Osiris Spiral (H) (214HK)', 'Throne of Isis (236LK)', 'Dive of Horus (236MK)', 'Weight of Anubis (236HK)', "Warrior's Khopesh (214LP)", "Butcher's Blade (214MP)", "Carpenter's Axe (214HP)", 'Lower Domain (Throw)', 'Lower Domain (Back Throw)', 'Dash (PP)', 'Backdash (4PP)', 'Keep young and beautiful! (Taunt) (Start)'] },
        { id: 10, name: 'Double', checkboxId: 'skullgirls-Double', colorCount: 28, moves: ['s.LP - Standing Jab', 's.MP - Strong Pie', 's.HP - Fugazi Knuckle', 's.LK - Substitute Short', 's.MK - Too Forward', 's.HK - Impawster', 'c.LP - Crouching Jab', 'c.MP - Elbow Emulator', 'c.HP - Double Drawn Weave', 'c.LK - Stamp', 'c.MK - Cliché', 'c.HK - Sweeping Generalization', 'Flesh Step (214K)', 'Luger Replica (L) (236LP)', 'Luger Replica (M) (236MP)', 'Luger Replica (H) (236HP)', 'Hornet Bomber (L) (623LK)', 'Hornet Bomber (M) (623MK)', 'Hornet Bomber (H) (623HK)', 'Cilia Slide (4LK+HK)', 'Godhand (Throw)', 'Godhand (Back Throw)', 'Dash (PP)', 'Backdash (4PP)', 'Defiling maggots... (Taunt) (Start)'] },
        { id: 11, name: 'Big Band', checkboxId: 'skullgirls-BigBand', colorCount: 29, moves: ['s.LP - Honky Tonk', 's.MP - Free Form', 's.HP - Air Mail Special', 's.LK - Hot Socks', 's.MK - Pneumatic Slide', 's.HK - Kick Stand', 'c.LP - Ring-a-Ding', 'c.MP - Glissando', 'c.HP - Overblow', 'c.LK - Sharp Note', 'c.MK - Bass Drop', 'c.HK - Low Rank', 'Beat Extend (L) (623LP)', 'Beat Extend (M) (623MP)', 'Beat Extend (H) (623HP)', 'Brass Knuckles (L) (46PK)', 'Brass Knuckles (M) (46MP)', 'Brass Knuckles (H) (46HP)', "Take the 'A' Train (L) (46LK)", "Take the 'A' Train (M) (46MK)", "Take the 'A' Train (H) (46HK)", 'Giant Step (L) (214LK)', 'Giant Step (M) (214MK)', 'Giant Step (H) (214HK)', 'Heavy Toll (Throw)', 'Heavy Toll (Back Throw)', 'Dash (PP)', 'Backdash (4PP)', 'Bagpipe Blues (Taunt) (Start)'] },
        { id: 12, name: 'Fukua', checkboxId: 'skullgirls-Fukua', colorCount: 29, moves: ['s.LP - Standing Jab', 's.MP - Thinning Shears', 's.HP - Chompadour', 's.LK - Knee High', 's.MK - Fukua sets mode +k', 's.HK - Shamone Boot', 'c.LP - Crouching Jab', 'c.MP - Ariel Rave', 'c.HP - Queue Sting', 'c.LK - Ankle Sock', 'c.MK - French Twist', 'c.HK - Tread of Hair', 'Love Dart (L) (236LP)', 'Love Dart (M) (236MP)', 'Love Dart (H) (236HP)', 'Forever a Clone (L) (214LK)', 'Forever a Clone (M) (214MK)', 'Forever a Clone (H) (214HK)', 'Platonic Drillationship (L) (236LK)', 'Platonic Drillationship (M) (236MK)', 'Platonic Drillationship (H) (236HK)', 'Tender Embrace (236LK+LP)', 'Inevitable Snuggle (214LK+LP)', 'Shamone Cuddle (Throw)', 'Shamone Cuddle (Back Throw)', 'Dash (PP)', 'Backdash (4PP)', 'Breakdown (Taunt) (Start)'] },
        { id: 13, name: 'Beowulf', checkboxId: 'skullgirls-Beowulf', colorCount: 28, moves: ['s.LP - Cheap Pop', 's.MP - Pipe Bomb', 's.HP - Hurting Hammer', 's.LK - Low Town', 's.MK - Wulf Kick', 's.HK - Lone Boot', 'c.LP - Wulf Paw', 'c.MP - Potato', 'c.HP - Nosebleed Seat', 'c.LK - Trepak Attack', 'c.MK - Ankle Lace', 'c.HK - Geatish Leg Sweep', 'Hurting Hurl (L) (236LP)', 'Hurting Hurl (M) (236MP)', 'Hurting Hurl (H) (236HP)', 'Wulf Blitzer (L) (236LK)', 'Wulf Blitzer (M) (236MK)', 'Wulf Blitzer (H) (236HK)', 'Wulf Shoot (236LK+LP)', 'Clinch Up (Throw) ', 'Clinch Up (Back Throw) ', 'Dash (PP)', 'Backdash (4PP)', 'Aroo Ready? (Taunt)'] },
        { id: 14, name: 'Annie', checkboxId: 'skullgirls-Annie', colorCount: 31, moves: ['s.LP - Ceres', 's.MP - Main Sequence', 's.HP - Binary System', 's.LK - Kuiper Belt', 's.MK - Cassiopeian Gambit', 's.HK - Liftoff', 'c.LP - Sedna', 'c.MP - Meathook Galaxy', 'c.HP - Retrograde Slice', 'c.LK - Boötes Void', 'c.MK - Hobble Telescope', 'c.HK - Big Dipper', 'f.MP - Andromeda', 'f.HP - Luminous Supergiant', 'Crescent Cut (L) (236LP)', 'Crescent Cut (M) (236MP)', 'Crescent Cut (H) (236HP)', 'North Knuckle (L) (214LP)', 'North Knuckle (M) (214MP)', 'North Knuckle(H) (214HP)', 'Destruction Pillar (L) (623LP)', 'Destruction Pillar (M) (623MP)', 'Destruction Pillar (H) (623HP)', "Sagan's Paradox (Throw)", "Sagan's Paradox (Back Throw)", 'Dash (PP)', 'Backdash (4PP)', 'Reflection Nebula (Taunt) (Start)'] },
        { id: 15, name: 'Umbrella', checkboxId: 'skullgirls-Umbrella', colorCount: 31, moves: ['s.LP - Lips Stick', 's.MP - Skewer Spewer', 's.HP - Grand Slam', 's.LK - Cupkick', 's.MK - Shin Dig', 's.HK - Rough Housing', 'c.LP - Mlem', 'c.MP - Tongue Lash', 'c.HP - Salt Lick', 'c.LK - Rain Boot', 'c.MK - Puddle Stomp', 'c.HK - See You Next Fall', 's.F + LP - Tight Squeeze', 's.F + HP - Cliff Hanger', 'Salt Grinder (236LP)', "Slurp'n'Slide (46MP)", 'Hungern Rush (28HP)', 'Cutie Ptooie (214LK)', 'Bobblin\' Bubble (214MK)', 'Wish Maker (214HK)', 'Tongue Twister (46LK+LP)', 'Appetizer (Throw)', 'Appetizer (Back Throw)', 'Dash (PP)', 'Backdash (4PP)', 'Hug It Out (Taunt) (Start)'] },
        { id: 16, name: 'Black Dahlia', checkboxId: 'skullgirls-BlackDahlia', colorCount: 31, moves: ['s.LP - Business and Pleasure', 's.MP - Serrated Edge', 's.HP - Skull Cracker', 's.LK - Shin Splitter', 's.MK - Stiletto Stab', 's.HK - Highball Heel', 'c.LP - Tchotchke Roulette', 'c.MP - Swan Strike', 'c.HP - Quake', 'c.LK - Blade Waltz', 'c.MK - Medici Mousetrap', 'c.HK - Clean Sweep', 'Order Up! (L) (236LP)', 'Order Up! (M) (236MP)', 'Order Up! (H) (236HP)', 'Another Round (LP) (214LP)', 'Another Round (MP) (214MP)', 'Another Round (HP) (214HP)', 'Tea Time (L) (214LK)', 'Tea Time (M) (214MK)', 'Tea Time (H) (214HK)', 'Onslaught (K+K)', 'Shakedown (Throw) (5/6LP+LK)', 'Shakedown (Backthrow) (4LP+LK', 'Dash (PP)', 'Backdash (4PP)', "It's Apple Juice (Taunt) (Start)", 'Empower (236LP+LK)'] },
        { id: 17, name: 'Marie', checkboxId: 'skullgirls-Marie', colorCount: 6, moves: ['s.LP - Rattan Luck', 's.MP - Backhanded Compliment', 's.HP - Clean Cut', 's.LK - Voided Warranty', 's.MK - Batter-ment', 's.HK - Helping Hands', 'c.LP - Scrub Scrubber', 'c.MP - Pain Cushion', 'c.HP - Dead Lift', 'c.LK - Bellows Blast', 'c.MK - Tidying Up', 'c.HK - Literally a Sweep', 'Hop To It (L) (236LP)', 'Hop To It (M) (236MP)', 'Hop To It (H) (236HP)', "Hilgard's Howl (L) (214LK)", "Hilgard's Howl (M) (214MK)", "Hilgard's Howl (H) (214HK)", "Hilgard's Haymaker (L) (236LK)", "Hilgard's Haymaker (M) (236MK)", "Hilgard's Haymaker (H) (236HK)", "Marie Go 'Round (L) (214LP)", "Marie Go 'Round (M) (214MP)", "Marie Go 'Round (H) (214HP)", 'Suction Obstruction (236LPLK)', 'May I Cut In? (Throw)', 'May I Cut In? (Back Throw)', 'Dash (PP)', 'Backdash (4PP)', "A Moment's Time (Taunt) (Start)"] },
    ];

    const colorImages = {
        1: 'https://wiki.gbl.gg/images/0/04/SGColor1.png',
        2: 'https://wiki.gbl.gg/images/9/9c/SGColor2.png',
        3: 'https://wiki.gbl.gg/images/a/a7/SGColor3.png',
        4: 'https://wiki.gbl.gg/images/5/5f/SGColor4.png',
        5: 'https://wiki.gbl.gg/images/a/af/SGColor5.png',
        6: 'https://wiki.gbl.gg/images/5/58/SGColor6.png',
        7: 'https://wiki.gbl.gg/images/4/4e/SGColor7.png',
        8: 'https://wiki.gbl.gg/images/5/53/SGColor8.png',
        9: 'https://wiki.gbl.gg/images/f/fb/SGColor9.png',
        10: 'https://wiki.gbl.gg/images/8/80/SGColor10.png',
        11: 'https://wiki.gbl.gg/images/f/fc/SGColor11.png',
        12: 'https://wiki.gbl.gg/images/f/f0/SGColor12.png',
        13: 'https://wiki.gbl.gg/images/f/f2/SGColor13.png',
        14: 'https://wiki.gbl.gg/images/2/26/SGColor14.png',
        15: 'https://wiki.gbl.gg/images/3/3f/SGColor15.png',
        16: 'https://wiki.gbl.gg/images/c/c8/SGColor16.png',
        17: 'https://wiki.gbl.gg/images/c/c1/SGColor17.png',
        18: 'https://wiki.gbl.gg/images/f/fb/SGColor18.png',
        19: 'https://wiki.gbl.gg/images/4/4f/SGColor19.png',
        20: 'https://wiki.gbl.gg/images/5/55/SGColor20.png',
        21: 'https://wiki.gbl.gg/images/e/e2/SGColor21.png',
        22: 'https://wiki.gbl.gg/images/d/d2/SGColor22.png',
        23: 'https://wiki.gbl.gg/images/3/3b/SGColor23.png',
        24: 'https://wiki.gbl.gg/images/6/64/SGColor24.png',
        25: 'https://wiki.gbl.gg/images/7/74/SGColor25.png',
        26: 'https://wiki.gbl.gg/images/8/86/SGColor26.png',
        27: 'https://wiki.gbl.gg/images/6/60/SGColor27.png',
        28: 'https://wiki.gbl.gg/images/d/d0/SGColor28.png',
        29: 'https://wiki.gbl.gg/images/2/29/SGColor29.png',
        30: 'https://wiki.gbl.gg/images/f/f5/SGColor30.png',
        31: 'https://wiki.gbl.gg/images/d/df/SGColor31.png',
    };
    const colorImage = (n) => colorImages[n];

    function generateTeam() {
        const roster = characters.filter((character) => document.getElementById(character.checkboxId).checked);

        if (roster.length < 3) {
            messageEl.textContent = 'Please select at least 3 characters.';
            return;
        }

        messageEl.textContent = '';

        const forced = [0, 1, 2].map((slot) => Number(document.getElementById(`skullgirls-select-${slot}`).value));
        const picked = [];

        for (let slot = 0; slot < 3; slot++) {
            let candidate = forced[slot];

            while (candidate === -1 || picked.includes(candidate)) {
                candidate = roster[randomInt(roster.length)].id;
            }

            picked.push(candidate);

            const character = characters.find((item) => item.id === candidate);
            const color = 1 + randomInt(character.colorCount);

            document.getElementById(`skullgirls-name-${slot}`).textContent = `${character.name}: ${randomElement(character.moves)}`;
            document.getElementById(`skullgirls-portrait-${slot}`).src = colorImage(color);
        }
    }

    button.addEventListener('click', generateTeam);
}

/* === Dengeki Bunko: Fighting Climax === */
function initDengekiRandomizer() {
    const button = document.getElementById('dengeki-new-team');
    if (! button) return;

    const characters = [
        { name: 'Akira', image: 'https://wiki.gbl.gg/images/thumb/4/47/Dfci_icon_Akira.png/90px-Dfci_icon_Akira.png' },
        { name: 'Ako', image: 'https://wiki.gbl.gg/images/thumb/8/85/Dfci_icon_Ako.png/90px-Dfci_icon_Ako.png' },
        { name: 'Asuna', image: 'https://wiki.gbl.gg/images/thumb/c/ca/Dfci_icon_Asuna.png/90px-Dfci_icon_Asuna.png' },
        { name: 'Emi', image: 'https://wiki.gbl.gg/images/thumb/6/64/Dfci_icon_Emi.png/90px-Dfci_icon_Emi.png' },
        { name: 'Kirino', image: 'https://wiki.gbl.gg/images/thumb/7/76/Dfci_icon_Kirino.png/90px-Dfci_icon_Kirino.png' },
        { name: 'Kirito', image: 'https://wiki.gbl.gg/images/thumb/8/8f/Dfci_icon_Kirito.png/90px-Dfci_icon_Kirito.png' },
        { name: 'Kuroko', image: 'https://wiki.gbl.gg/images/thumb/5/5e/Dfci_icon_Kuroko.png/90px-Dfci_icon_Kuroko.png' },
        { name: 'Kuroyukihime', image: 'https://wiki.gbl.gg/images/thumb/f/f7/Dfci_icon_Kuroyukihime.png/90px-Dfci_icon_Kuroyukihime.png' },
        { name: 'Mikoto', image: 'https://wiki.gbl.gg/images/thumb/1/1f/Dfci_icon_Mikoto.png/90px-Dfci_icon_Mikoto.png' },
        { name: 'Miyuki', image: 'https://wiki.gbl.gg/images/thumb/f/fa/Dfci_icon_Miyuki.png/90px-Dfci_icon_Miyuki.png' },
        { name: 'Quenser', image: 'https://wiki.gbl.gg/images/thumb/1/18/Dfci_icon_Quenser.png/90px-Dfci_icon_Quenser.png' },
        { name: 'Rentaro', image: 'https://wiki.gbl.gg/images/thumb/7/75/Dfci_icon_Rentaro.png/90px-Dfci_icon_Rentaro.png' },
        { name: 'Selvaria', image: 'https://wiki.gbl.gg/images/thumb/4/47/Dfci_icon_Selvaria.png/90px-Dfci_icon_Selvaria.png' },
        { name: 'Shana', image: 'https://wiki.gbl.gg/images/thumb/e/ea/Dfci_icon_Shana.png/90px-Dfci_icon_Shana.png' },
        { name: 'Shizuo', image: 'https://wiki.gbl.gg/images/thumb/5/5e/Dfci_icon_Shizuo.png/90px-Dfci_icon_Shizuo.png' },
        { name: 'Taiga', image: 'https://wiki.gbl.gg/images/thumb/7/78/Dfci_icon_Taiga.png/90px-Dfci_icon_Taiga.png' },
        { name: 'Tatsuya', image: 'https://wiki.gbl.gg/images/thumb/b/bc/Dfci_icon_Tatsuya.png/90px-Dfci_icon_Tatsuya.png' },
        { name: 'Tomoka', image: 'https://wiki.gbl.gg/images/thumb/8/8c/Dfci_icon_Tomoka.png/90px-Dfci_icon_Tomoka.png' },
        { name: 'Yukina', image: 'https://wiki.gbl.gg/images/thumb/e/e8/Dfci_icon_Yukina.png/90px-Dfci_icon_Yukina.png' },
        { name: 'Yuuki', image: 'https://wiki.gbl.gg/images/thumb/8/85/Dfci_icon_Yuuki.png/90px-Dfci_icon_Yuuki.png' },
    ];

    const assists = [
        { name: 'Accelerator', image: 'https://wiki.gbl.gg/images/thumb/7/79/Dfci_support_icon_Accelerator.png/90px-Dfci_support_icon_Accelerator.png' },
        { name: 'Alicia', image: 'https://wiki.gbl.gg/images/thumb/a/af/Dfci_support_icon_Alicia.png/90px-Dfci_support_icon_Alicia.png' },
        { name: 'Boogiepop', image: 'https://wiki.gbl.gg/images/thumb/4/42/Dfci_support_icon_Boogiepop.png/90px-Dfci_support_icon_Boogiepop.png' },
        { name: 'Celty', image: 'https://wiki.gbl.gg/images/thumb/7/7f/Dfci_support_icon_Celty.png/90px-Dfci_support_icon_Celty.png' },
        { name: 'Dokuro', image: 'https://wiki.gbl.gg/images/thumb/b/b1/Dfci_support_icon_Dokuro.png/90px-Dfci_support_icon_Dokuro.png' },
        { name: 'Enju', image: 'https://wiki.gbl.gg/images/thumb/6/6c/Dfci_support_icon_Enju.png/90px-Dfci_support_icon_Enju.png' },
        { name: 'Erio', image: 'https://wiki.gbl.gg/images/thumb/9/9e/Dfci_support_icon_Erio.png/90px-Dfci_support_icon_Erio.png' },
        { name: 'Froleytia', image: 'https://wiki.gbl.gg/images/thumb/b/b2/Dfci_support_icon_Froleytia.png/90px-Dfci_support_icon_Froleytia.png' },
        { name: 'Haruyuki', image: 'https://wiki.gbl.gg/images/thumb/6/6d/Dfci_support_icon_Haruyuki.png/90px-Dfci_support_icon_Haruyuki.png' },
        { name: 'Holo', image: 'https://wiki.gbl.gg/images/thumb/5/50/Dfci_support_icon_Holo.png/90px-Dfci_support_icon_Holo.png' },
        { name: 'Innocent Charm', image: 'https://wiki.gbl.gg/images/thumb/c/c9/Dfci_support_icon_Innocent_Charm.png/90px-Dfci_support_icon_Innocent_Charm.png' },
        { name: 'Iriya', image: 'https://wiki.gbl.gg/images/thumb/3/30/Dfci_support_icon_Iriya.png/90px-Dfci_support_icon_Iriya.png' },
        { name: 'Izaya', image: 'https://wiki.gbl.gg/images/thumb/e/ef/Dfci_support_icon_Izaya.png/90px-Dfci_support_icon_Izaya.png' },
        { name: 'Kino', image: 'https://wiki.gbl.gg/images/thumb/a/a5/Dfci_support_icon_Kino.png/90px-Dfci_support_icon_Kino.png' },
        { name: 'Kojou', image: 'https://wiki.gbl.gg/images/thumb/4/4a/Dfci_support_icon_Kojou.png/90px-Dfci_support_icon_Kojou.png' },
        { name: 'Kouko', image: 'https://wiki.gbl.gg/images/thumb/9/99/Dfci_support_icon_Kouko.png/90px-Dfci_support_icon_Kouko.png' },
        { name: 'Kuroneko', image: 'https://wiki.gbl.gg/images/thumb/1/10/Dfci_support_icon_Kuroneko.png/90px-Dfci_support_icon_Kuroneko.png' },
        { name: 'Leafa', image: 'https://wiki.gbl.gg/images/thumb/9/9a/Dfci_support_icon_Leafa.png/90px-Dfci_support_icon_Leafa.png' },
        { name: 'LLENN', image: 'https://wiki.gbl.gg/images/thumb/0/05/Dfci_support_icon_LLENN.png/90px-Dfci_support_icon_LLENN.png' },
        { name: 'Mashiro', image: 'https://wiki.gbl.gg/images/thumb/9/98/Dfci_support_icon_Mashiro.png/90px-Dfci_support_icon_Mashiro.png' },
        { name: 'Miyuki', image: 'https://wiki.gbl.gg/images/thumb/b/b2/Dfci_support_icon_Miyuki.png/90px-Dfci_support_icon_Miyuki.png' },
        { name: 'Pai', image: 'https://wiki.gbl.gg/images/thumb/6/66/Dfci_support_icon_Pai.png/90px-Dfci_support_icon_Pai.png' },
        { name: 'Rusian', image: 'https://wiki.gbl.gg/images/thumb/8/89/Dfci_support_icon_Rusian.png/90px-Dfci_support_icon_Rusian.png' },
        { name: 'Ryuuji', image: 'https://wiki.gbl.gg/images/thumb/8/8b/Dfci_support_icon_Ryuuji.png/90px-Dfci_support_icon_Ryuuji.png' },
        { name: 'Sadao', image: 'https://wiki.gbl.gg/images/thumb/8/89/Dfci_support_icon_Sadao.png/90px-Dfci_support_icon_Sadao.png' },
        { name: 'Tatsuya', image: 'https://wiki.gbl.gg/images/thumb/d/d8/Dfci_support_icon_Tatsuya.png/90px-Dfci_support_icon_Tatsuya.png' },
        { name: 'Touma', image: 'https://wiki.gbl.gg/images/thumb/9/92/Dfci_support_icon_Touma.png/90px-Dfci_support_icon_Touma.png' },
        { name: 'Tomo', image: 'https://wiki.gbl.gg/images/thumb/0/0a/Dfci_support_icon_Tomo.png/90px-Dfci_support_icon_Tomo.png' },
        { name: 'Uihara', image: 'https://wiki.gbl.gg/images/thumb/6/62/Dfci_support_icon_Uihara.png/90px-Dfci_support_icon_Uihara.png' },
        { name: 'Wilhelmina', image: 'https://wiki.gbl.gg/images/thumb/a/a8/Dfci_support_icon_Wilhelmina.png/90px-Dfci_support_icon_Wilhelmina.png' },
        { name: 'Zero', image: 'https://wiki.gbl.gg/images/thumb/7/79/Dfci_support_icon_Zero.png/90px-Dfci_support_icon_Zero.png' },
    ];

    function generateTeam() {
        const character = randomElement(characters);
        const assist = randomElement(assists);

        document.getElementById('dengeki-name').textContent = `${character.name} + ${assist.name}`;
        document.getElementById('dengeki-portrait-0').src = character.image;
        document.getElementById('dengeki-portrait-1').src = assist.image;
        document.getElementById('dengeki-color-0').textContent = `Color ${1 + randomInt(24)}`;
        document.getElementById('dengeki-color-1').textContent = `Color ${1 + randomInt(24)}`;
    }

    button.addEventListener('click', generateTeam);
    generateTeam();
}

initDbfzRandomizer();
initMvc2Randomizer();
initSkullgirlsRandomizer();
initDengekiRandomizer();
