<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scuderia Ferrari Exhibition</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script type="importmap">
        {
            "imports": {
                "three": "https://unpkg.com/three@0.160.0/build/three.module.js",
                "three/addons/": "https://unpkg.com/three@0.160.0/examples/jsm/"
            }
        }
    </script>
</head>
<body>

    <video src="assets/videos/video.mp4" autoplay loop muted playsinline id="bg-video"></video>

    <nav class="navbar">
        <div class="nav-left">
            <img src="https://upload.wikimedia.org/wikipedia/fr/thumb/c/c0/Scuderia_Ferrari_Logo.svg/500px-Scuderia_Ferrari_Logo.svg.png?_=20250316140006" alt="Ferrari Logo" class="brand-logo">
            <span class="brand-name">Ferrari</span>
        </div>
        <div class="nav-right">
            <a href="#" title="Contact"><i class="fa-solid fa-envelope"></i></a>
            <a href="#" title="Modèles"><i class="fa-solid fa-car"></i></a>
            <a href="#" title="Compte personnel"><i class="fa-solid fa-user"></i></a>
            <a href="#" title="Déconnexion"><i class="fa-solid fa-right-from-bracket"></i></a>
        </div>
    </nav>

    <main class="showroom-container">
        
        <div id="webgl-canvas-container"></div>

        <div class="ui-panel top-left-panel">
            <h1 id="car-title">Monza SP3 Evo</h1>
            <h2 id="car-subtitle">L'Équilibre Absolu du V12</h2>
            <button id="car-badge" class="rounded-btn">Édition Limitée</button>
        </div>

        <button id="arrow-prev" class="nav-arrow prev"><i class="fa-solid fa-chevron-left"></i></button>
        <button id="arrow-next" class="nav-arrow next"><i class="fa-solid fa-chevron-right"></i></button>

        <div class="dots-navigation">
            <span class="dot active" data-index="0"></span>
            <span class="dot" data-index="1"></span>
            <span class="dot" data-index="2"></span>
        </div>

        <div class="ui-panel bottom-left-panel">
            <div id="car-specs" class="specs-box">
                </div>
        </div>

        <div class="ui-panel bottom-right-panel">
            <h3 class="panel-title">Fiche Technique</h3>
            <p id="car-desc">Inspirée des mythiques barquettes de compétition des années 1960.</p>
            
            <button id="play-sound-btn" class="rounded-btn sound-btn">
                <i class="fa-solid fa-volume-high"></i> Écouter le moteur
            </button>

            <button id="open-panel-btn" class="rounded-btn primary-btn">Ouvrir les Détails</button>
        </div>

    </main>

    <div id="side-panel" class="side-panel">
        <div class="side-panel-header">
            <h2 id="panel-car-title">Spécifications</h2>
            <button id="close-panel-btn" class="close-btn">&times;</button>
        </div>
        <div class="side-panel-body">
            <img src="assets/src/images/FT.png" alt="Fiche Technique Spécifications" class="tech-sheet-img">
        </div>
    </div>

    <script type="module" src="assets/js/main.js"></script>
</body>
</html>