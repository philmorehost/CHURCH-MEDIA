(function () {
  'use strict';

  /* ---------- Church name correction flag toggle ---------- */
  var flagToggle = document.querySelector('[data-flag-toggle]');
  var flagForm = document.querySelector('[data-flag-form]');
  if (flagToggle && flagForm) {
    flagToggle.addEventListener('click', function () {
      var hidden = flagForm.style.display === 'none';
      flagForm.style.display = hidden ? '' : 'none';
      flagToggle.textContent = hidden ? 'Close correction form' : '🏷 Church name wrong? Report the correct spelling';
    });
  }

  /* ---------- Province > Zone > Area cascade + Parish suggestions ----------
     Each container carries data-units (nested org tree) and data-old (previous
     selection, if the page re-rendered after an error). The parish input is
     auto-CAPS'd and, when it matches an existing parish under the chosen Area,
     its id is stored (reuse) — otherwise it stays empty (a new parish created
     on approval). */
  document.querySelectorAll('[data-units]').forEach(function (root) {
    var nodes = [];
    try { nodes = JSON.parse(root.getAttribute('data-units') || '[]'); } catch (e) { nodes = []; }
    var old = {};
    try { old = JSON.parse(root.getAttribute('data-old') || '{}'); } catch (e) { old = {}; }

    var selProvince = root.querySelector('[data-province]');
    var selZone = root.querySelector('[data-zone]');
    var selArea = root.querySelector('[data-area]');
    var parishInput = root.querySelector('[data-parish]');
    var parishList = root.querySelector('[data-parish-list]');
    var hidProvince = root.querySelector('[data-province-id]');
    var hidZone = root.querySelector('[data-zone-id]');
    var hidArea = root.querySelector('[data-area-id]');
    var hidParishId = root.querySelector('[data-parish-id]');
    var hidParishName = root.querySelector('[data-parish-name]');
    if (!selProvince || !selZone || !selArea) { return; }

    function childrenOf(parentId) {
      if (!parentId) { return nodes; }
      for (var i = 0; i < nodes.length; i++) {
        var found = findNode(nodes[i], parentId);
        if (found) { return found.children || []; }
      }
      return [];
    }
    function findNode(node, id) {
      if (node.id === id) { return node; }
      for (var i = 0; i < (node.children || []).length; i++) {
        var r = findNode(node.children[i], id);
        if (r) { return r; }
      }
      return null;
    }
    function parishesOf(areaId) {
      return childrenOf(areaId).filter(function (n) { return n.type === 'parish'; });
    }
    function fill(sel, opts, placeholder) {
      sel.innerHTML = '';
      var ph = document.createElement('option');
      ph.value = '';
      ph.textContent = placeholder;
      sel.appendChild(ph);
      opts.forEach(function (o) {
        var opt = document.createElement('option');
        opt.value = String(o.id);
        opt.textContent = o.name;
        sel.appendChild(opt);
      });
    }
    function sync() {
      var pid = parseInt(selProvince.value, 10) || 0;
      var zid = parseInt(selZone.value, 10) || 0;
      var aid = parseInt(selArea.value, 10) || 0;
      if (hidProvince) { hidProvince.value = pid ? String(pid) : ''; }
      if (hidZone) { hidZone.value = zid ? String(zid) : ''; }
      if (hidArea) { hidArea.value = aid ? String(aid) : ''; }

      if (parishList && parishInput) {
        parishList.innerHTML = '';
        parishesOf(aid).forEach(function (p) {
          var opt = document.createElement('option');
          opt.value = p.name;
          parishList.appendChild(opt);
        });
        syncParish();
      }
    }
    function syncParish() {
      if (!parishInput) { return; }
      var aid = parseInt(selArea.value, 10) || 0;
      var name = (parishInput.value || '').trim().toUpperCase();
      var match = null;
      parishesOf(aid).forEach(function (p) { if (p.name === name) { match = p; } });
      if (hidParishId) { hidParishId.value = match ? String(match.id) : ''; }
      if (hidParishName) { hidParishName.value = name; }
    }

    function buildZone() {
      var pid = parseInt(selProvince.value, 10) || 0;
      fill(selZone, childrenOf(pid), 'Select Zone…');
      if (old.zone_id) { selZone.value = String(old.zone_id); }
      buildArea();
    }
    function buildArea() {
      var zid = parseInt(selZone.value, 10) || 0;
      fill(selArea, childrenOf(zid), 'Select Area…');
      if (old.area_id) { selArea.value = String(old.area_id); }
      sync();
    }

    // Province list is static (top of tree).
    fill(selProvince, nodes, 'Select Province…');
    if (old.province_id) { selProvince.value = String(old.province_id); }

    // Pre-fill from server-side "old" values (re-render after an error, or the
    // admin review form loading an existing registration).
    if (old.parish_name) {
      // wait until areas are built to set the parish value
    }
    buildZone();
    if (old.parish_name && parishInput) { parishInput.value = old.parish_name; }
    syncParish();

    selProvince.addEventListener('change', function () { old.zone_id = 0; old.area_id = 0; old.parish_id = 0; old.parish_name = ''; buildZone(); });
    selZone.addEventListener('change', function () { old.area_id = 0; old.parish_id = 0; old.parish_name = ''; buildArea(); });
    selArea.addEventListener('change', function () { old.parish_id = 0; old.parish_name = ''; sync(); });

    if (parishInput) {
      parishInput.addEventListener('input', function () {
        parishInput.value = parishInput.value.toUpperCase();
        syncParish();
      });
      parishInput.addEventListener('change', syncParish);
    }
  });
})();
