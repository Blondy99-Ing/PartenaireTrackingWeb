{{-- resources/views/trajets/byVoiture.blade.php --}}
@extends('layouts.app')

@section('title', 'Trajets sur carte')

@push('head')
<style>
  #map{
    width:100%;
    height: calc(100vh - 260px);
    min-height: 520px;
    border-radius: 14px;
  }

  .map-shell{ position:relative; }

  .map-top-actions{
    position:absolute;
    top:14px; right:14px;
    z-index: 20;
    display:flex; gap:10px;
  }

  .floating-btn{
    display:inline-flex; align-items:center; gap:8px;
    padding: 10px 12px;
    border-radius: 999px;
    border: 1px solid rgba(255,255,255,.14);
    background: rgba(0,0,0,.22);
    color: #fff;
    backdrop-filter: blur(6px);
    cursor:pointer;
    transition:.15s;
    user-select:none;
    font-size: 13px;
    box-shadow: 0 10px 24px rgba(0,0,0,.18);
  }
  .floating-btn:hover{ transform: translateY(-1px); border-color: rgba(245,130,32,.9); }
  .floating-btn.active{ border-color: rgba(245,130,32,.95); background: rgba(245,130,32,.22); }

  .floating-panel{
    position:absolute;
    top:64px; right:14px;
    z-index: 20;
    width: min(360px, calc(100% - 28px));
    display:none;
  }

  .panel-card{
    border-radius: 16px;
    background: rgba(0,0,0,.30);
    color:#fff;
    border: 1px solid rgba(255,255,255,.14);
    backdrop-filter: blur(10px);
    padding: 12px;
    box-shadow: 0 16px 40px rgba(0,0,0,.22);
  }

  .panel-title{
    display:flex; align-items:center; justify-content:space-between;
    gap:10px; margin-bottom: 10px;
    font-weight: 800;
  }

  .mini-actions{ display:flex; gap:8px; flex-wrap:wrap; }

  .mini-btn{
    display:inline-flex; align-items:center; gap:8px;
    padding: 8px 10px;
    border-radius: 12px;
    border: 1px solid rgba(255,255,255,.14);
    background: rgba(255,255,255,.06);
    color:#fff;
    cursor:pointer;
    font-size: 12px;
    transition:.15s;
    user-select:none;
  }
  .mini-btn:hover{ transform: translateY(-1px); border-color: rgba(245,130,32,.9); }
  .mini-btn.active{ border-color: rgba(245,130,32,.95); background: rgba(245,130,32,.18); }

  .speed-pill{
    display:inline-flex; align-items:center; justify-content:center;
    padding: 6px 10px;
    border-radius: 999px;
    border:1px solid rgba(255,255,255,.14);
    background: rgba(255,255,255,.06);
    font-weight:800;
    min-width: 64px;
  }

  .muted{ opacity:.78; font-size: 12px; }

  .progress{
    width:100%;
    height: 8px;
    border-radius: 999px;
    background: rgba(255,255,255,.12);
    overflow:hidden;
    margin-top: 10px;
  }
  .progress > div{
    height:100%;
    width: 0%;
    background: rgba(245,130,32,.85);
    transition: width .08s linear;
  }

  @media(max-width: 767px){
    #map{ height: calc(100vh - 320px); min-height: 460px; border-radius: 12px; }
    .map-top-actions{ top:10px; right:10px; }
    .floating-panel{ top:56px; right:10px; width: calc(100% - 20px); }
  }
</style>

{{-- ✅ Boot Google Maps solide : callback peut arriver avant DOM --}}
<script>
  window.__gm_ready = false;
  window.__dom_ready = false;
  window.__startMap = null;

  window.initMap = function () {
    window.__gm_ready = true;
    try {
      if (typeof window.__startMap === 'function' && window.__dom_ready) window.__startMap();
    } catch (e) { console.error('[Trajets] initMap crash:', e); }
  };

  document.addEventListener('DOMContentLoaded', () => {
    window.__dom_ready = true;
    try {
      if (typeof window.__startMap === 'function' && window.__gm_ready) window.__startMap();
    } catch (e) { console.error('[Trajets] DOM crash:', e); }
  });
</script>

<script
  src="https://maps.googleapis.com/maps/api/js?key={{ config('services.google_maps.key') }}&callback=initMap&libraries=geometry"
  async defer></script>
@endpush

@section('content')
@php
  $filters = $filters ?? request()->all();
  $focusId = $focusId ?? request('focus_trajet_id');
@endphp

<div class="max-w-7xl mx-auto p-2 space-y-4">

  <header class="flex items-center justify-between gap-3 flex-wrap">
    <div class="space-y-2">
      <h1 class="text-2xl md:text-3xl font-orbitron font-bold" style="color: var(--color-text);">
        Trajets de <span class="text-primary">{{ $voiture->immatriculation }}</span>
      </h1>
      @if($focusId)
        <div class="text-sm text-secondary">
          <i class="fas fa-crosshairs text-primary mr-1"></i>
          Trajet sélectionné : <b>#{{ $focusId }}</b>
        </div>
      @endif
    </div>

    <a href="{{ route('trajets.index', $filters) }}"
       class="btn-secondary py-2 px-4 text-sm">
      <i class="fas fa-arrow-left mr-2"></i> Retour
    </a>
  </header>

  {{-- FILTRES (conserve ton style actuel) --}}
  <div class="ui-card p-4">
    <form method="GET" action="{{ url()->current() }}" class="grid grid-cols-1 md:grid-cols-6 gap-4 items-end">
      <div class="md:col-span-2">
        <label class="text-sm font-medium text-secondary">Filtrer par</label>
        <select id="filter-type" name="quick" class="ui-input-style w-full">
          <option value="today"    {{ request('quick','today')=='today'?'selected':'' }}>Aujourd'hui</option>
          <option value="yesterday"{{ request('quick')=='yesterday'?'selected':'' }}>Hier</option>
          <option value="week"     {{ request('quick')=='week'?'selected':'' }}>Cette semaine</option>
          <option value="month"    {{ request('quick')=='month'?'selected':'' }}>Ce mois</option>
          <option value="year"     {{ request('quick')=='year'?'selected':'' }}>Cette année</option>
          <option value="date"     {{ request('quick')=='date'?'selected':'' }}>Date spécifique</option>
          <option value="range"    {{ request('quick')=='range'?'selected':'' }}>Plage de dates</option>
        </select>
      </div>

      <div id="single-date" class="hidden md:col-span-2">
        <label class="text-sm font-medium text-secondary">Date</label>
        <input id="dateInput" type="date" name="date" class="ui-input-style w-full" value="{{ request('date') }}">
      </div>

      <div id="date-range" class="hidden md:col-span-2">
        <label class="text-sm font-medium text-secondary">Plage de dates</label>
        <div class="grid grid-cols-2 gap-3">
          <input id="startDateInput" type="date" name="start_date" class="ui-input-style" value="{{ request('start_date') }}">
          <input id="endDateInput" type="date" name="end_date" class="ui-input-style" value="{{ request('end_date') }}">
        </div>
      </div>

      <div class="md:col-span-2">
        <label class="text-sm font-medium text-secondary">Heures</label>
        <div class="grid grid-cols-2 gap-3">
          <input type="time" name="start_time" class="ui-input-style" value="{{ request('start_time') }}">
          <input type="time" name="end_time" class="ui-input-style" value="{{ request('end_time') }}">
        </div>
      </div>

      <div class="md:col-span-6 flex justify-end">
        <button class="btn-primary px-8 h-[42px] flex items-center justify-center">
          <i class="fas fa-filter mr-2"></i> Filtrer
        </button>
      </div>
    </form>
  </div>

  {{-- MAP + MODE/REPLAY --}}
  <div class="map-shell">
    <div class="map-top-actions">
      <button type="button" class="floating-btn" id="btnMode">
        <i class="fas fa-layer-group"></i> Mode
      </button>
      <button type="button" class="floating-btn" id="btnReplay">
        <i class="fas fa-play-circle"></i> Replay
      </button>
    </div>

    {{-- MODE PANEL --}}
    <div class="floating-panel" id="panelMode">
      <div class="panel-card">
        <div class="panel-title">
          <div><i class="fas fa-layer-group mr-2 text-primary"></i> Mode</div>
          <button type="button" class="mini-btn" data-close="panelMode"><i class="fas fa-times"></i></button>
        </div>

        <div class="mini-actions">
          <button type="button" class="mini-btn active" data-maptype="roadmap">Plan</button>
          <button type="button" class="mini-btn" data-maptype="hybrid">Hybride</button>
          <button type="button" class="mini-btn" data-maptype="satellite">Satellite</button>
          <button type="button" class="mini-btn" data-maptype="terrain">Terrain</button>
        </div>

        <div class="mini-actions" style="margin-top:10px">
          <button type="button" class="mini-btn" id="btnTraffic"><i class="fas fa-traffic-light"></i> Trafic</button>
          <button type="button" class="mini-btn" id="btnLocate"><i class="fas fa-crosshairs"></i> Ma position</button>
        </div>

        <div class="muted" style="margin-top:10px">
          Disparaît 5s après sortie souris. Pendant replay : plus rapide.
        </div>
      </div>
    </div>

    {{-- REPLAY PANEL --}}
    <div class="floating-panel" id="panelReplay">
      <div class="panel-card">
        <div class="panel-title">
          <div><i class="fas fa-play-circle mr-2 text-primary"></i> Replay</div>
          <button type="button" class="mini-btn" data-close="panelReplay"><i class="fas fa-times"></i></button>
        </div>

        <div class="mini-actions" style="justify-content:space-between; width:100%">
          <div class="mini-actions">
            <button type="button" class="mini-btn" id="rpPrev" title="Précédent"><i class="fas fa-step-backward"></i></button>
            <button type="button" class="mini-btn" id="rpPlay" title="Play"><i class="fas fa-play"></i></button>
            <button type="button" class="mini-btn" id="rpPause" title="Pause"><i class="fas fa-pause"></i></button>
            <button type="button" class="mini-btn" id="rpStop" title="Stop"><i class="fas fa-stop"></i></button>
            <button type="button" class="mini-btn" id="rpNext" title="Suivant"><i class="fas fa-step-forward"></i></button>
          </div>

          <div class="mini-actions">
            <button type="button" class="mini-btn" id="rpSlow" title="Ralentir"><i class="fas fa-minus"></i></button>
            <span class="speed-pill" id="rpSpeed">x1</span>
            <button type="button" class="mini-btn" id="rpFast" title="Accélérer"><i class="fas fa-plus"></i></button>
          </div>
        </div>

        <div class="muted" style="margin-top:10px">
          <div><b>Heure :</b> <span id="rpTime">—</span></div>
          <div id="rpCoords">—</div>
          <div><b>Vitesse :</b> <span id="rpV">—</span></div>
        </div>

        <div class="progress"><div id="rpBar"></div></div>
      </div>
    </div>

    <div id="map" class="shadow-md border border-border-subtle"></div>
  </div>

  {{-- Résumé --}}
  <div class="ui-card flex flex-wrap justify-around text-center">
    <div class="p-3">
      <p class="text-3xl font-orbitron text-primary">{{ $trajets->count() }}</p>
      <p class="text-sm text-secondary">Trajets</p>
    </div>
    <div class="p-3">
      <p class="text-3xl font-orbitron text-primary">{{ $totalDistance }} km</p>
      <p class="text-sm text-secondary">Distance totale</p>
    </div>
    <div class="p-3">
      @php $h=floor($totalDuration/60); $m=$totalDuration%60; @endphp
      <p class="text-3xl font-orbitron text-primary">{{ $h }}h {{ $m }}m</p>
      <p class="text-sm text-secondary">Durée totale</p>
    </div>
    <div class="p-3">
      <p class="text-3xl font-orbitron text-primary">{{ $maxSpeed }} km/h</p>
      <p class="text-sm text-secondary">Vitesse max</p>
    </div>
    <div class="p-3">
      <p class="text-3xl font-orbitron text-primary">{{ $avgSpeed }} km/h</p>
      <p class="text-sm text-secondary">Vitesse moyenne</p>
    </div>
  </div>

</div>

{{-- UI filter select --}}
<script>
document.addEventListener("DOMContentLoaded", () => {
  const type = document.getElementById("filter-type");
  const single = document.getElementById("single-date");
  const range  = document.getElementById("date-range");
  const dateInput = document.getElementById("dateInput");
  const startDateInput = document.getElementById("startDateInput");
  const endDateInput = document.getElementById("endDateInput");

  function updateUI(){
    single?.classList.add("hidden");
    range?.classList.add("hidden");
    if(type?.value === "date") single?.classList.remove("hidden");
    if(type?.value === "range") range?.classList.remove("hidden");
  }
  updateUI();
  type?.addEventListener("change", updateUI);

  // auto submit sur date/range si tu veux (optionnel)
  dateInput?.addEventListener('change', ()=>{ if(type.value==='date') dateInput.form.submit(); });
  [startDateInput,endDateInput].forEach(el=>el?.addEventListener('change', ()=>{ if(type.value==='range') el.form.submit(); }));
});
</script>

{{-- MAP + TRACKS + REPLAY --}}
<script>
window.__startMap = function bootMap(){
  try{
    const mapDiv = document.getElementById('map');
    if(!mapDiv) return;
    if(!window.google || !google.maps) return;

    const tracksRaw = @json($tracks ?? []);
    const focusId = @json($focusId);

    const btnMode = document.getElementById('btnMode');
    const btnReplay = document.getElementById('btnReplay');
    const panelMode = document.getElementById('panelMode');
    const panelReplay = document.getElementById('panelReplay');

    const btnTraffic = document.getElementById('btnTraffic');
    const btnLocate = document.getElementById('btnLocate');
    const mapTypeBtns = Array.from(document.querySelectorAll('[data-maptype]'));

    const rpPrev  = document.getElementById('rpPrev');
    const rpPlay  = document.getElementById('rpPlay');
    const rpPause = document.getElementById('rpPause');
    const rpStop  = document.getElementById('rpStop');
    const rpNext  = document.getElementById('rpNext');
    const rpSlow  = document.getElementById('rpSlow');
    const rpFast  = document.getElementById('rpFast');
    const rpSpeed = document.getElementById('rpSpeed');
    const rpTime  = document.getElementById('rpTime');
    const rpCoords= document.getElementById('rpCoords');
    const rpV     = document.getElementById('rpV');
    const rpBar   = document.getElementById('rpBar');

    const primary = (getComputedStyle(document.documentElement).getPropertyValue('--color-primary') || '').trim() || '#F58220';

    // ----------------------------
    // Panels: click only + autohide
    // ----------------------------
    window.__replayPlaying = false;

    function makeAutoHide(panelEl, getPlaying){
      let timer=null;
      let inside=false;

      function schedule(ms){
        clearTimeout(timer);
        timer=setTimeout(()=>{ if(!inside) panelEl.style.display='none'; }, ms);
      }

      panelEl.addEventListener('mouseenter', ()=>{ inside=true; clearTimeout(timer); });
      panelEl.addEventListener('mouseleave', ()=>{
        inside=false;
        schedule(getPlaying() ? 800 : 5000);
      });

      panelEl.__schedule = schedule;
    }

    if(panelMode) makeAutoHide(panelMode, ()=>window.__replayPlaying);
    if(panelReplay) makeAutoHide(panelReplay, ()=>window.__replayPlaying);

    function togglePanel(panelEl, otherEl){
      if(!panelEl) return;
      const open = panelEl.style.display === 'block';
      if(otherEl) otherEl.style.display = 'none';
      panelEl.style.display = open ? 'none' : 'block';
      if(!open) panelEl.__schedule && panelEl.__schedule(1400);
    }

    btnMode?.addEventListener('click', (e)=>{ e.stopPropagation(); togglePanel(panelMode, panelReplay); });
    btnReplay?.addEventListener('click', (e)=>{ e.stopPropagation(); togglePanel(panelReplay, panelMode); });

    document.querySelectorAll('[data-close]').forEach(x=>{
      x.addEventListener('click', ()=>{
        const id=x.getAttribute('data-close');
        const el=document.getElementById(id);
        if(el) el.style.display='none';
      });
    });

    document.addEventListener('click', (e)=>{
      const insideMode = panelMode && panelMode.contains(e.target);
      const insideReplay = panelReplay && panelReplay.contains(e.target);
      const isBtn = (btnMode && btnMode.contains(e.target)) || (btnReplay && btnReplay.contains(e.target));
      if(insideMode || insideReplay || isBtn) return;
      if(panelMode) panelMode.style.display='none';
      if(panelReplay) panelReplay.style.display='none';
    });

    // ----------------------------
    // Map init
    // ----------------------------
    let center = { lat: 4.05, lng: 9.7 };
    for(const tr of (tracksRaw||[])){
      if(tr?.points?.length){ center={lat:+tr.points[0].lat, lng:+tr.points[0].lng}; break; }
    }

    const map = new google.maps.Map(mapDiv, {
      zoom: 13,
      center,
      mapTypeId: "roadmap",
      mapTypeControl: false,
      fullscreenControl: true,
      streetViewControl: true,
      clickableIcons: true,
      gestureHandling: "greedy"
    });

    // traffic
    const trafficLayer = new google.maps.TrafficLayer();
    let trafficOn=false;
    btnTraffic?.addEventListener('click', ()=>{
      trafficOn=!trafficOn;
      trafficLayer.setMap(trafficOn ? map : null);
      btnTraffic.classList.toggle('active', trafficOn);
    });

    // map types
    mapTypeBtns.forEach(btn=>{
      btn.addEventListener('click', ()=>{
        mapTypeBtns.forEach(b=>b.classList.remove('active'));
        btn.classList.add('active');
        map.setMapTypeId(btn.getAttribute('data-maptype'));
      });
    });

    // locate
    let myMarker=null, myCircle=null;
    btnLocate?.addEventListener('click', ()=>{
      if(!navigator.geolocation) return;
      navigator.geolocation.getCurrentPosition((pos)=>{
        const lat=pos.coords.latitude, lng=pos.coords.longitude, acc=pos.coords.accuracy||0;
        const latLng=new google.maps.LatLng(lat,lng);
        map.panTo(latLng);
        if(map.getZoom()<16) map.setZoom(16);

        if(!myMarker){
          myMarker=new google.maps.Marker({
            map, position:latLng, title:"Ma position",
            icon:{ path:google.maps.SymbolPath.CIRCLE, fillColor:"#2563eb", fillOpacity:1, strokeColor:"#fff", strokeWeight:2, scale:9 }
          });
        } else myMarker.setPosition(latLng);

        if(!myCircle){
          myCircle=new google.maps.Circle({ map, center:latLng, radius:acc, strokeOpacity:.2, fillOpacity:.08 });
        } else { myCircle.setCenter(latLng); myCircle.setRadius(acc); }
      }, ()=>{}, { enableHighAccuracy:true, timeout:8000 });
    });

    // ----------------------------
    // Points correction (soft) + segmentation ONLY when not focus
    // ----------------------------
    function haversineMeters(a,b){
      const R=6371000, toRad=x=>x*Math.PI/180;
      const dLat=toRad(b.lat-a.lat), dLng=toRad(b.lng-a.lng);
      const lat1=toRad(a.lat), lat2=toRad(b.lat);
      const s=Math.sin(dLat/2)**2 + Math.cos(lat1)*Math.cos(lat2)*Math.sin(dLng/2)**2;
      return 2*R*Math.atan2(Math.sqrt(s), Math.sqrt(1-s));
    }

    function safeTimeMs(t){
      if(!t) return null;
      const iso=String(t).replace(' ','T');
      const ms=Date.parse(iso);
      return Number.isNaN(ms) ? null : ms;
    }

    // filtre léger : enlève doublons + énormes jumps absurdes
    function cleanPoints(raw){
      const pts=(raw||[]).map(p=>({lat:+p.lat,lng:+p.lng,t:p.t||null,speed:+(p.speed||0)}));
      if(pts.length<2) return pts;

      const out=[];
      let prev=null;
      let prevKey=null;

      const MIN_MOVE_M=2;
      const MAX_KMH=170;          // plus permissif
      const MAX_JUMP_M=600;       // plus permissif
      const MAX_JUMP_S=8;

      for(const p of pts){
        const key = p.lat.toFixed(6)+','+p.lng.toFixed(6);
        if(key===prevKey) continue;
        prevKey=key;

        if(!prev){ out.push(p); prev=p; continue; }

        const d=haversineMeters(prev,p);
        if(d<MIN_MOVE_M) continue;

        const t1=safeTimeMs(prev.t), t2=safeTimeMs(p.t);
        if(t1!=null && t2!=null){
          const dt=Math.abs(t2-t1)/1000;
          if(dt>0){
            const v=(d/dt)*3.6;
            if(v>MAX_KMH) continue;
            if(d>MAX_JUMP_M && dt<=MAX_JUMP_S) continue;
          }else{
            if(d>MAX_JUMP_M) continue;
          }
        }else{
          if(d>MAX_JUMP_M*2) continue;
        }

        out.push(p);
        prev=p;
      }
      return out;
    }

    function splitSegments(points){
      const segs=[];
      if(points.length<2) return segs;
      let seg=[points[0]];
      for(let i=1;i<points.length;i++){
        const a=points[i-1], b=points[i];
        const d=haversineMeters(a,b);
        // seuil raisonnable pour éviter les "traits à travers la ville" en liste
        if(d>350){
          if(seg.length>=2) segs.push(seg);
          seg=[b];
        }else seg.push(b);
      }
      if(seg.length>=2) segs.push(seg);
      return segs;
    }

    function prepareTrack(tr){
      const pts = cleanPoints(tr.points||[]);
      const segments = splitSegments(pts);
      return {...tr, __pts: pts, __segments: segments};
    }

    const tracks = (tracksRaw||[]).map(prepareTrack);

    // draw
    const boundsAll = new google.maps.LatLngBounds();

    function circleIcon(fill){
      return { path: google.maps.SymbolPath.CIRCLE, fillColor: fill, fillOpacity: 1, strokeColor: "#fff", strokeWeight: 2, scale: 9 };
    }

    tracks.forEach(tr=>{
      const isFocus = focusId && String(tr.trajet_id)===String(focusId);

      // ✅ IMPORTANT : en focus -> PAS de segmentation => un seul trait continu
      const segs = isFocus ? [ (tr.__pts||[]) ] : (tr.__segments||[]);
      if(!segs.length) return;

      segs.forEach(seg=>{
        if(seg.length<2) return;
        const path = seg.map(p=>({lat:p.lat,lng:p.lng}));
        path.forEach(p=>boundsAll.extend(p));

        new google.maps.Polyline({
          path,
          strokeColor: primary,
          strokeOpacity: isFocus ? 1 : 0.82,
          strokeWeight: isFocus ? 7 : 4,
          geodesic: true,
          map
        });

        // start/end markers only for focus (sinon ça spam)
        if(isFocus){
          new google.maps.Marker({ position:path[0], map, icon: circleIcon("#16a34a"), label:{text:"D",color:"#fff",fontWeight:"800"} });
          new google.maps.Marker({ position:path[path.length-1], map, icon: circleIcon("#dc2626"), label:{text:"A",color:"#fff",fontWeight:"800"} });
        }
      });
    });

    // fit bounds
    if(!boundsAll.isEmpty()){
      map.fitBounds(boundsAll, 40);
      google.maps.event.addListenerOnce(map,"idle",()=>{ if(map.getZoom()>18) map.setZoom(18); });
    }

    // ----------------------------
    // Replay (lent + fluide)
    // ----------------------------
    let currentTrack=null;
    let currentPoints=[];
    let idx=0;
    let timer=null;
    let marker=null;
    let trail=null;

    // ✅ plus lent : x1 réellement lent
    const speedSteps=[1,2,4,6,8,12,16];
    let speedIndex=0; // x1
    const speedMult=()=>speedSteps[speedIndex] || 1;

    function updateSpeedUI(){ if(rpSpeed) rpSpeed.textContent=`x${speedMult()}`; }
    updateSpeedUI();

    function ensureReplay(){
      if(marker) return;
      marker = new google.maps.Marker({
        map,
        position: map.getCenter(),
        title: "Replay",
        icon: { path:google.maps.SymbolPath.CIRCLE, fillColor: primary, fillOpacity:1, strokeColor:"#fff", strokeWeight:2, scale:8 }
      });
      trail = new google.maps.Polyline({
        map,
        path: [],
        strokeColor: primary,
        strokeOpacity: 0.9,
        strokeWeight: 5,
        geodesic: true
      });
    }

    function pause(){
      window.__replayPlaying=false;
      if(timer){ clearInterval(timer); timer=null; }
    }

    function stop(){
      pause();
      idx=0;
      if(trail) trail.setPath([]);
      if(rpBar) rpBar.style.width='0%';
      if(rpTime) rpTime.textContent='—';
      if(rpCoords) rpCoords.textContent='—';
      if(rpV) rpV.textContent='—';
    }

    function stepTo(i){
      if(!currentPoints.length) return;
      idx = Math.max(0, Math.min(currentPoints.length-1, i));
      const p=currentPoints[idx];
      ensureReplay();
      const pos=new google.maps.LatLng(p.lat,p.lng);
      marker.setPosition(pos);

      // ✅ follow center
      map.panTo(pos);

      // trail
      const path=trail.getPath();
      path.push(pos);
      if(path.getLength()>2500) path.removeAt(0);

      if(rpTime) rpTime.textContent = p.t || '—';
      if(rpCoords) rpCoords.textContent = `Lat ${p.lat.toFixed(6)} • Lng ${p.lng.toFixed(6)}`;
      if(rpV) rpV.textContent = `${Number(p.speed||0).toFixed(1)} km/h`;

      const pct=(idx/Math.max(1,currentPoints.length-1))*100;
      if(rpBar) rpBar.style.width = `${pct.toFixed(2)}%`;
    }

    // ✅ tick plus lent (interval plus long + step petit)
    function tick(){
      const mult = speedMult();

      // step = 1 point à x1, 2 points à x2, etc (pas agressif)
      const step = Math.max(1, Math.round(mult/2));

      stepTo(idx + step);
      if(idx >= currentPoints.length-1) pause();
    }

    function play(){
      if(!currentPoints.length) return;
      window.__replayPlaying=true;

      // règle : au play -> panel se cache
      if(panelReplay) panelReplay.style.display='none';

      if(timer) clearInterval(timer);

      // ✅ 220ms -> x1 lent
      timer = setInterval(tick, 220);
    }

    function selectTrackForReplay(tr){
      currentTrack=tr;
      currentPoints=(tr.__pts || []).slice();
      if(currentPoints.length<2) return;
      stop();
      stepTo(0);

      // ouvre brièvement le panel au choix
      if(panelReplay){
        panelReplay.style.display='block';
        panelReplay.__schedule && panelReplay.__schedule(1400);
      }
    }

    // default track : focus si présent, sinon premier
    const defaultTrack = focusId ? tracks.find(x=>String(x.trajet_id)===String(focusId)) : (tracks[0]||null);
    if(defaultTrack) selectTrackForReplay(defaultTrack);

    rpPlay?.addEventListener('click', ()=>play());
    rpPause?.addEventListener('click', ()=>pause());
    rpStop?.addEventListener('click', ()=>stop());

    rpPrev?.addEventListener('click', ()=>{ pause(); stepTo(idx-30); });
    rpNext?.addEventListener('click', ()=>{ pause(); stepTo(idx+30); });

    rpSlow?.addEventListener('click', ()=>{ speedIndex=Math.max(0,speedIndex-1); updateSpeedUI(); });
    rpFast?.addEventListener('click', ()=>{ speedIndex=Math.min(speedSteps.length-1,speedIndex+1); updateSpeedUI(); });

  }catch(e){
    console.error('[Trajets] bootMap crash:', e);
  }
};
</script>
@endsection