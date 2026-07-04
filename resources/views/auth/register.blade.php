<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription Employé - ProxyM (Dual Mode)</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Police Orbitron -->
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;600;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --color-primary: #F58220;
            --font-family: 'Orbitron', sans-serif;
        }
        .font-orbitron { font-family: var(--font-family); }

        /* --- LIGHT MODE --- */
        .light-mode {
            --color-bg: #f3f4f6;
            --color-card: #ffffff;
            --color-text: #111827;
            --color-input-bg: #ffffff;
            --color-input-border: #d1d5db;
            --color-secondary-text: #6b7280;
            background-color: var(--color-bg);
            color: var(--color-text);
        }
        .light-mode .card-shadow { box-shadow: 0 10px 30px rgba(0,0,0,0.1); border-color: #e5e7eb; background-color: var(--color-card);}
        .light-mode .input-style { background-color: var(--color-input-bg); border-color: var(--color-input-border); color: var(--color-text); }
        .light-mode .file-input-style::file-selector-button { background-color: #e5e7eb; color: var(--color-text); }
        .light-mode .text-primary { color: var(--color-primary); }
        .light-mode .text-secondary { color: var(--color-secondary-text); }
        .light-mode .placeholder-color::placeholder { color: #9ca3af; }

        /* --- DARK MODE --- */
        .dark-mode {
            --color-bg: #121212;
            --color-card: #1f2937;
            --color-text: #f3f4f6;
            --color-input-bg: #374151;
            --color-input-border: #4b5563;
            --color-secondary-text: #9ca3af;
            background-color: var(--color-bg);
            color: var(--color-text);
        }
        .dark-mode .card-shadow { box-shadow: 0 15px 40px rgba(0,0,0,0.5); border-color: #374151; background-color: var(--color-card);}
        .dark-mode .input-style { background-color: var(--color-input-bg); border-color: var(--color-input-border); color: var(--color-text); }
        .dark-mode .file-input-style::file-selector-button { background-color: #4b5563; color: var(--color-text); }
        .dark-mode .text-primary { color: var(--color-primary); }
        .dark-mode .text-secondary { color: var(--color-secondary-text); }
        .dark-mode .placeholder-color::placeholder { color: #6b7280; }

        /* --- Commun --- */
        .input-style:focus { border-color: var(--color-primary) !important; box-shadow: 0 0 0 3px rgba(245,130,32,0.4);}
        .btn-primary { background-color: var(--color-primary); transition: 0.2s; }
        .btn-primary:hover { background-color: #e06d12; transform: translateY(-1px);}
        .btn-secondary { color: var(--color-primary); border:1px solid var(--color-primary); transition: 0.2s; background-color: transparent;}
        .btn-secondary:hover { background-color: rgba(245,130,32,0.1); }
        .file-input-style { cursor: pointer; transition: 0.2s; }
        .toggle-switch { width:48px;height:24px;background:#4b5563;border-radius:9999px;position:relative;cursor:pointer;transition:0.4s;}
        .toggle-switch.toggled { background: var(--color-primary); }
        .toggle-switch::after { content:''; position:absolute; top:2px; left:2px; width:20px;height:20px; background:#fff; border-radius:9999px; transition:0.4s; }
        .toggle-switch.toggled::after { transform: translateX(24px);}
    </style>
</head>
<body class="flex items-center justify-center min-h-screen p-4 light-mode" id="theme-container">
<div class="w-full max-w-4xl mx-auto">
    <!-- Toggle Mode -->
    <div class="flex justify-end mb-4">
        <span id="mode-label" class="text-sm mr-2 pt-0.5 text-secondary font-orbitron hidden md:block">Mode Clair</span>
        <div id="theme-toggle" class="toggle-switch"></div>
    </div>

    <!-- Carte Formulaire -->
    <div class="card-shadow p-8 md:p-10 rounded-xl border">
        <header class="text-center mb-8">
            <div class="font-orbitron text-xl md:text-2xl font-extrabold">
                PROXYM <span class="text-primary">TRACKING</span>
            </div>
            <h1 class="font-orbitron text-2xl md:text-3xl font-bold mt-4">Ajouter un Nouvel Employé</h1>
            <p class="text-sm text-secondary mt-1">Créez un compte employé sécurisé.</p>
        </header>

        <form id="employe-form" class="space-y-6">
            <!-- Informations de base -->
            <h2 class="font-orbitron text-lg font-semibold border-b pb-2 mb-4 text-primary border-primary">Informations de base</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="nom" class="block text-sm font-medium font-orbitron">Nom <span class="text-red-500">*</span></label>
                    <input type="text" id="nom" name="nom" required class="mt-1 block w-full px-4 py-2 border rounded-lg input-style placeholder-color" placeholder="Nom de famille">
                </div>
                <div>
                    <label for="prenom" class="block text-sm font-medium font-orbitron">Prénom <span class="text-red-500">*</span></label>
                    <input type="text" id="prenom" name="prenom" required class="mt-1 block w-full px-4 py-2 border rounded-lg input-style placeholder-color" placeholder="Prénom de l'employé">
                </div>
            </div>

            <div>
                <label for="email" class="block text-sm font-medium font-orbitron">Email <span class="text-red-500">*</span></label>
                <input type="email" id="email" name="email" required class="mt-1 block w-full px-4 py-2 border rounded-lg input-style placeholder-color" placeholder="utilisateur@agence.com">
            </div>

            <!-- Contact & localisation -->
            <h2 class="font-orbitron text-lg font-semibold border-b pb-2 mb-4 pt-4 text-primary border-primary">Contact & Localisation</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <label for="phone" class="block text-sm font-medium font-orbitron">Téléphone (Optionnel)</label>
                    <input type="tel" id="phone" name="phone" class="mt-1 block w-full px-4 py-2 border rounded-lg input-style placeholder-color" placeholder="+33 6...">
                </div>
                <div>
                    <label for="ville" class="block text-sm font-medium font-orbitron">Ville (Optionnel)</label>
                    <input type="text" id="ville" name="ville" class="mt-1 block w-full px-4 py-2 border rounded-lg input-style placeholder-color" placeholder="Ville">
                </div>
                <div>
                    <label for="quartier" class="block text-sm font-medium font-orbitron">Quartier (Optionnel)</label>
                    <input type="text" id="quartier" name="quartier" class="mt-1 block w-full px-4 py-2 border rounded-lg input-style placeholder-color" placeholder="Quartier">
                </div>
            </div>

            <!-- Photo -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 pt-4 items-center">
                <div class="md:col-span-2">
                    <label for="photo" class="block text-sm font-medium font-orbitron">Photo de Profil (Optionnel)</label>
                    <input type="file" id="photo" name="photo" accept="image/*" class="mt-1 block w-full file-input-style" onchange="previewImage(event)">
                    <p class="text-xs text-secondary mt-1">Format: JPG, PNG, max 2MB.</p>
                </div>
                <div class="md:col-span-1 flex justify-center md:justify-end">
                    <img id="image-preview" src="https://placehold.co/100x100/F58220/ffffff?text=Photo" alt="Prévisualisation" class="w-24 h-24 object-cover rounded-full border-2 border-dashed border-primary">
                </div>
            </div>

            <!-- Sécurité -->
            <h2 class="font-orbitron text-lg font-semibold border-b pb-2 mb-4 pt-4 text-primary border-primary">Sécurité</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="password" class="block text-sm font-medium font-orbitron">Mot de passe <span class="text-red-500">*</span></label>
                    <input type="password" id="password" name="password" required class="mt-1 block w-full px-4 py-2 border rounded-lg input-style placeholder-color" placeholder="••••••••">
                </div>
                <div>
                    <label for="password_confirmation" class="block text-sm font-medium font-orbitron">Confirmer le Mot de passe <span class="text-red-500">*</span></label>
                    <input type="password" id="password_confirmation" name="password_confirmation" required class="mt-1 block w-full px-4 py-2 border rounded-lg input-style placeholder-color" placeholder="••••••••">
                </div>
            </div>

            <!-- Boutons -->
            <div class="flex flex-col md:flex-row justify-end gap-3 pt-4">
                <button type="button" onclick="resetForm()" class="btn-secondary px-6 py-2 rounded-lg font-orbitron font-semibold text-sm">Annuler / Réinitialiser</button>
                <button type="submit" class="btn-primary text-white px-8 py-2 rounded-lg font-orbitron font-bold text-sm shadow-md shadow-orange-500/50">Enregistrer l'Employé</button>
            </div>
        </form>

        <!-- Messages -->
        <div id="message-box" class="mt-6 p-4 rounded-lg hidden text-sm font-semibold font-orbitron" role="alert"></div>
    </div>
</div>

<script>
// --- Variables globales ---
const themeContainer = document.getElementById('theme-container');
const themeToggle = document.getElementById('theme-toggle');
const modeLabel = document.getElementById('mode-label');
const imagePreview = document.getElementById('image-preview');
const form = document.getElementById('employe-form');
const messageBox = document.getElementById('message-box');

// --- Thème ---
function setTheme(theme){
    if(theme==='dark'){
        themeContainer.classList.replace('light-mode','dark-mode');
        themeToggle.classList.add('toggled'); 
        modeLabel.textContent='Mode Sombre';
        if(imagePreview.src.includes('ffffff?text=Photo')) imagePreview.src="https://placehold.co/100x100/374151/AAAAAA?text=Photo";
    } else{
        themeContainer.classList.replace('dark-mode','light-mode');
        themeToggle.classList.remove('toggled'); 
        modeLabel.textContent='Mode Clair';
        if(imagePreview.src.includes('AAAAAA?text=Photo')) imagePreview.src="https://placehold.co/100x100/F58220/ffffff?text=Photo";
    }
    localStorage.setItem('theme',theme);
}
themeToggle.addEventListener('click',()=>{setTheme(themeContainer.classList.contains('dark-mode')?'light':'dark');});
document.addEventListener('DOMContentLoaded',()=>setTheme(localStorage.getItem('theme')||'light'));

// --- Messages ---
function displayMessage(msg,type='info'){
    const theme = themeContainer.classList.contains('dark-mode')?'dark':'light';
    messageBox.textContent=msg; 
    messageBox.style.display='block';
    messageBox.className='mt-6 p-4 rounded-lg text-sm font-semibold font-orbitron';
    if(type==='success') messageBox.classList.add(...(theme==='dark'?['bg-green-700','text-green-100']:['bg-green-100','text-green-800']));
    else if(type==='error') messageBox.classList.add(...(theme==='dark'?['bg-red-700','text-red-100']:['bg-red-100','text-red-800']));
    else messageBox.classList.add(...(theme==='dark'?['bg-blue-700','text-blue-100']:['bg-blue-100','text-blue-800']));
    messageBox.scrollIntoView({behavior:'smooth'});
}

// --- Prévisualisation image ---
function previewImage(e){
    const file=e.target.files[0];
    const dark=themeContainer.classList.contains('dark-mode');
    if(file){
        const reader=new FileReader();
        reader.onload=function(ev){imagePreview.src=ev.target.result;}
        reader.readAsDataURL(file);
    } else { 
        imagePreview.src=dark?"https://placehold.co/100x100/374151/AAAAAA?text=Photo":"https://placehold.co/100x100/F58220/ffffff?text=Photo"; 
    }
}

// --- Envoi formulaire ---
form.addEventListener('submit', async function(e) {
    e.preventDefault();
    messageBox.style.display = 'none';

    const formData = new FormData(form);

    try {
        const response = await fetch("/register", {
            method: "POST",
            headers: {
                "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: formData
        });

        const text = await response.text();
        let data = {};
        try { data = text ? JSON.parse(text) : {}; } 
        catch(e) { 
            displayMessage("❌ Réponse du serveur invalide.", 'error');
            return;
        }

        if(response.ok && data.success){
            displayMessage("✅ " + (data.message || "Employé enregistré avec succès !"), 'success');
            
            // Prévisualisation si l'utilisateur a uploadé une photo
            if(data.employe.photo){
                imagePreview.src = `/storage/${data.employe.photo}`;
            }

            // Réinitialiser tout le formulaire sauf la photo affichée
            form.reset();
        } else if(data.errors){
            const messages = Object.values(data.errors).flat().join(' ');
            displayMessage("❌ " + messages, 'error');
        } else {
            displayMessage(`❌ Échec de l'enregistrement. Code HTTP: ${response.status}`, 'error');
        }

    } catch (error) {
        displayMessage("❌ Une erreur s'est produite lors de l'enregistrement.", 'error');
    }
});

// --- Reset formulaire ---
function resetForm(){
    form.reset(); 
    messageBox.style.display='none';
    const dark=themeContainer.classList.contains('dark-mode');
    imagePreview.src=dark?"https://placehold.co/100x100/374151/AAAAAA?text=Photo":"https://placehold.co/100x100/F58220/ffffff?text=Photo";
}

</script>

</body>
</html>
