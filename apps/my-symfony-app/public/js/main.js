(function () {

    /*=====================================
    Sticky
    ======================================= */
    window.onscroll = function () {
        var header_navbar = document.querySelector(".navbar-area");
        var sticky = header_navbar.offsetTop;

        if (window.pageYOffset > sticky) {
            header_navbar.classList.add("sticky");
        } else {
            header_navbar.classList.remove("sticky");
        }



        // show or hide the back-top-top button
        var backToTo = document.querySelector(".scroll-top");
        if (document.body.scrollTop > 50 || document.documentElement.scrollTop > 50) {
            backToTo.style.display = "flex";
        } else {
            backToTo.style.display = "none";
        }
    };

    // section menu active
	function onScroll(event) {
		var sections = document.querySelectorAll('.page-scroll');
		var scrollPos = window.pageYOffset || document.documentElement.scrollTop || document.body.scrollTop;

		for (var i = 0; i < sections.length; i++) {
			var currLink = sections[i];
			var val = currLink.getAttribute('href');
			var refElement = document.querySelector(val);
			var scrollTopMinus = scrollPos + 73;
			if (refElement.offsetTop <= scrollTopMinus && (refElement.offsetTop + refElement.offsetHeight > scrollTopMinus)) {
				document.querySelector('.page-scroll').classList.remove('active');
				currLink.classList.add('active');
			} else {
				currLink.classList.remove('active');
			}
		}
	};

    window.document.addEventListener('scroll', onScroll);
    
    // for menu scroll 
    var pageLink = document.querySelectorAll('.page-scroll');

    pageLink.forEach(elem => {
        elem.addEventListener('click', e => {
            e.preventDefault();
            document.querySelector(elem.getAttribute('href')).scrollIntoView({
                behavior: 'smooth',
                offsetTop: 1 - 60,
            });
        });
    });

    "use strict";

    // Admin: delegation pour boutons dynamiques (templates injectés via AJAX)
    document.addEventListener('click', function(e){
        // Toggle Google Map in demande detail
        var target = e.target;
        // Support clicks on icon or text inside the button
        if (target.closest && target.closest('#toggleMapBtn')){
            var btn = target.closest('#toggleMapBtn');
            var container = document.getElementById('mapContainer');
            if (!container){ return; }
            var isHidden = (container.style.display === 'none' || container.style.display === '');
            container.style.display = isHidden ? 'block' : 'none';
            btn.textContent = isHidden ? 'Masquer la carte' : 'Afficher la carte';
            if (isHidden) {
                // Ensure Leaflet assets are loaded then init the map
                ensureLeaflet(function(){
                    initLeafletFromContainer();
                });
            }
        }
        // Map: search button (Leaflet + Nominatim)
        if (target.closest && target.closest('#mapSearchBtn')){
            var input = document.getElementById('mapSearchInput');
            if (!input){ return; }
            var q = (input.value || '').trim();
            if (!q){ return; }
            ensureLeaflet(function(){ leafletGeocodeAndCenter(q); });
        }
        // Map: use demande address (Leaflet + Nominatim)
        if (target.closest && target.closest('#mapUseAdresseBtn')){
            var input2 = document.getElementById('mapSearchInput');
            var adresse = document.getElementById('adresse_rejete');
            var zoneSelect = document.getElementById('id_zone');
            var nomPrenom = document.getElementById('nom_prenom');
            if (!input2){ return; }
            var zoneText = zoneSelect && zoneSelect.options[zoneSelect.selectedIndex] ? zoneSelect.options[zoneSelect.selectedIndex].text : '';
            var base = (adresse && adresse.value && adresse.value.trim()) ? adresse.value.trim() : (zoneText || (nomPrenom ? nomPrenom.value : ''));
            if (!base){ return; }
            input2.value = base;
            ensureLeaflet(function(){ leafletGeocodeAndCenter(base); });
        }
        // Back to list
        if (target.closest && target.closest('#backToListBtn')){
            window.location.href = '/admin';
        }
    });

    // Map: search on Enter key using delegation (Leaflet)
    document.addEventListener('keydown', function(e){
        var target = e.target;
        if (target && target.id === 'mapSearchInput' && e.key === 'Enter'){
            e.preventDefault();
            var q = (target.value || '').trim();
            if (!q){ return; }
            ensureLeaflet(function(){ leafletGeocodeAndCenter(q); });
        }
    });

}) ();

// Dynamically ensure Leaflet CSS/JS is loaded (works for AJAX-injected pages)
function ensureLeaflet(cb){
    if (window.L && typeof window.L.map === 'function') { cb && cb(); return; }
    // Ensure CSS in <head>
    if (!document.getElementById('leaflet-css')){
        var link = document.createElement('link');
        link.id = 'leaflet-css';
        link.rel = 'stylesheet';
        link.href = 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.css';
        document.head.appendChild(link);
    }
    // If a script with same id exists in body (from AJAX), it may not execute.
    // Always inject a dedicated dynamic script into <head> when L is missing.
    if (!window.L){
        var dyn = document.getElementById('leaflet-js-dyn');
        if (!dyn){
            var s = document.createElement('script');
            s.id = 'leaflet-js-dyn';
            s.src = 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.js';
            s.onload = function(){ cb && cb(); };
            document.head.appendChild(s);
        } else {
            if (typeof cb === 'function') {
                if (window.L) cb(); else dyn.addEventListener('load', function(){ cb(); });
            }
        }
        return;
    }
    cb && cb();
}

function initLeafletFromContainer(){
    var mapDiv = document.getElementById('leafletMap');
    if (!mapDiv) return;
    if (mapDiv._map){ setTimeout(function(){ mapDiv._map.invalidateSize(); }, 0); return; }
    if (!window.L || !L.map) return;
    var map = L.map('leafletMap');
    mapDiv._map = map;
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);
    var centres = [];
    try {
        var raw = mapDiv.getAttribute('data-centres') || '[]';
        centres = JSON.parse(raw);
    } catch(e){ centres = []; }
    mapDiv._centres = centres;
    var bounds = [];
    centres.forEach(function(c){
        var lat = parseFloat(c.lat), lng = parseFloat(c.lng);
        if (isFinite(lat) && isFinite(lng)){
            var m = L.marker([lat, lng]).addTo(map);
            var content = '<strong>' + (c.name || '') + '</strong><br/>' + (c.adresse || '');
            m.bindPopup(content);
            bounds.push([lat, lng]);
        }
    });
    var notice = document.getElementById('mapNotice');
    if (bounds.length){
        if (notice) notice.style.display = 'none';
        map.fitBounds(bounds, { padding: [20,20] });
    } else {
        if (notice) notice.style.display = 'block';
        map.setView([33.5731, -7.5898], 11);
    }
    setTimeout(function(){ map.invalidateSize(); }, 0);
}

// Geocode with Nominatim and recenter the Leaflet map
function leafletGeocodeAndCenter(query){
    if (!query) return;
    var mapDiv = document.getElementById('leafletMap');
    if (!mapDiv || !mapDiv._map) return;
    fetch('https://nominatim.openstreetmap.org/search?format=json&q=' + encodeURIComponent(query))
        .then(function(r){ return r.json(); })
        .then(function(results){
            if (results && results.length){
                var r0 = results[0];
                var lat = parseFloat(r0.lat), lon = parseFloat(r0.lon);
                if (isFinite(lat) && isFinite(lon)){
                    mapDiv._map.setView([lat, lon], 13);
                }
            }
        })
        .catch(function(err){ console.warn('Geocode error', err); });
}

// Recenter to centres bounds (if any), otherwise fallback view
function recenterLeafletToCentres(){
    var mapDiv = document.getElementById('leafletMap');
    if (!mapDiv || !mapDiv._map) return;
    var centres = Array.isArray(mapDiv._centres) ? mapDiv._centres : [];
    var bounds = [];
    centres.forEach(function(c){
        var lat = parseFloat(c.lat), lng = parseFloat(c.lng);
        if (isFinite(lat) && isFinite(lng)) bounds.push([lat, lng]);
    });
    var notice = document.getElementById('mapNotice');
    if (bounds.length){
        if (notice) notice.style.display = 'none';
        mapDiv._map.fitBounds(bounds, { padding: [20,20] });
    } else {
        if (notice) notice.style.display = 'block';
        mapDiv._map.setView([33.5731, -7.5898], 11);
    }
}

// Delegate click for recenter button
document.addEventListener('click', function(e){
    var t = e.target;
    if (t.closest && t.closest('#mapRecenterBtn')){
        ensureLeaflet(function(){ recenterLeafletToCentres(); });
    }
});
