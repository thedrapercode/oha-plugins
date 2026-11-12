
(function($){
  const acceptedKey = 'scareshiftCharterAcceptedV1';

  function fetchJSON(url){ return fetch(url, {cache:'no-store'}).then(r=>r.json()); }
  function getEl(id){ return document.getElementById(id); }

  async function ensureCharterModal() { if(!(SCARESHIFT.settings && SCARESHIFT.settings.enableCharter)) return; 
    try{
      if(localStorage.getItem(acceptedKey) === 'yes') return;
      const wrap = document.createElement('div');
      wrap.innerHTML = `<div class="scareshift-modal-backdrop" id="scareshift-modal-backdrop"></div>
        <div class="scareshift-modal" id="scareshift-charter-modal">
          <h2>ScareCraft Community Safety & Culture Charter</h2>
          <div class="scareshift-modal-body" id="scareshift-charter-body">Loading…</div>
          <div class="scareshift-actions">
            <button class="scareshift-btn" id="scareshift-accept-charter">I Accept</button>
          </div>
        </div>`;
      document.body.appendChild(wrap);
      const html = await fetch(SCARESHIFT.urls.charter, {cache:'no-store'}).then(r=>r.text());
      document.getElementById('scareshift-charter-body').innerHTML = html;
      const show = ()=>{
        document.getElementById('scareshift-modal-backdrop').style.display='block';
        document.getElementById('scareshift-charter-modal').style.display='block';
      };
      const hide = ()=>{
        document.getElementById('scareshift-modal-backdrop').style.display='none';
        document.getElementById('scareshift-charter-modal').style.display='none';
      };
      $('#scareshift-accept-charter').on('click', ()=>{
        localStorage.setItem(acceptedKey,'yes');
        hide();
      });
      show();
    } catch(e){ console.warn('Charter modal failed', e); }
  }

  function round05(x){ return Math.max(1, Math.min(10, Math.round(x*2)/2)); }

  function applyWeights(score, weights){
    if(!weights) return;
    for(const k in weights){
      if(typeof score[k] === 'number'){
        score[k] += weights[k];
      }
    }
  }

  function clampAndRound(score){
    for(const k in score){ score[k] = round05(score[k]); }
    return score;
  }

  function statSummary(s){ return `Fear ${s.fear} · Agi ${s.agility} · End ${s.endurance} · Voice ${s.voice} · Pres ${s.presence}`; }

  async function renderCreator(node){
    console.log('ScareShift: renderCreator called', node);
    console.log('ScareShift: SCARESHIFT object:', typeof SCARESHIFT !== 'undefined' ? SCARESHIFT : 'UNDEFINED!');
    
    try {
      await ensureCharterModal();

    node.innerHTML = `<div class="scareshift-row">
      <div class="scareshift-col">
        <div class="scareshift-card">
          <h3 class="scareshift-h">Examples for the Board</h3>
          <div class="scareshift-row" style="align-items:center">
            <select id="scareshift-sample" class="scareshift-select"></select>
            <button class="scareshift-btn" id="scareshift-load-sample">Load Sample</button>
          </div>
          <p class="scareshift-muted" id="scareshift-sample-note"></p>
        </div>
        <div class="scareshift-card" style="margin-top:12px">
          <h3 class="scareshift-h">Your Character</h3>
          <div id="scareshift-form"></div>
        </div>
      </div>
      <div class="scareshift-col">
        <div class="scareshift-card">
          <h3 class="scareshift-h">Character Sheet</h3>
          <div id="scareshift-sheet" class="scareshift-sheet"></div>
          <div class="scareshift-row" style="margin-top:10px">
            <button class="scareshift-btn" id="scareshift-export-pdf">Export PDF</button>
            <button class="scareshift-btn" id="scareshift-export-png">Export PNG</button>
            <button class="scareshift-btn" id="scareshift-save" title="Logged-in users only">Save</button>
          </div>
          <p class="scareshift-muted" id="scareshift-save-msg"></p>
        </div>
      </div>
    </div>`;

    const cfg = await fetchJSON(SCARESHIFT.urls.scoring);
    const samples = await fetchJSON(SCARESHIFT.urls.samples);

    // Populate samples
    const sel = document.getElementById('scareshift-sample');
    samples.list.forEach((s, i)=>{
      const opt = document.createElement('option');
      opt.value = i; opt.textContent = s.name;
      sel.appendChild(opt);
    });
    const noteEl = document.getElementById('scareshift-sample-note');
    function showSampleNote(){
      const s = samples.list[ sel.value ];
      noteEl.textContent = s ? (s.note||'') : '';
    }
    sel.addEventListener('change', showSampleNote);
    showSampleNote();

    // Build form from cfg.questions
    const formEl = document.getElementById('scareshift-form');
    function fieldId(qid){ return 'q_' + qid; }
    function renderQuestion(qid, q){
      const wrap = document.createElement('div');
      wrap.style.marginBottom = '10px';
      const title = document.createElement('div');
      title.innerHTML = `<b>${q.label||qid}</b>`;
      wrap.appendChild(title);
      if(q.type === 'single'){
        const select = document.createElement('select');
        select.className = 'scareshift-select';
        select.id = fieldId(qid);
        select.innerHTML = '<option value="">—</option>';
        Object.keys(q.options).forEach(k=>{
          const o = document.createElement('option');
          o.value = k; o.textContent = q.options[k];
          select.appendChild(o);
        });
        wrap.appendChild(select);
      } else if (q.type === 'multi'){
        const grid = document.createElement('div');
        grid.className = 'scareshift-grid-2';
        Object.keys(q.options).forEach(k=>{
          const id = fieldId(qid)+'_'+k;
          const box = document.createElement('div');
          box.innerHTML = `<label style="display:flex;gap:8px;align-items:center">
            <input type="checkbox" id="${id}" data-k="${k}" />
            <span>${q.options[k]}</span>
          </label>`;
          grid.appendChild(box);
        });
        wrap.appendChild(grid);
      } else if (q.type === 'composite'){
        Object.keys(q.parts).forEach(pk=>{
          const sub = q.parts[pk];
          const title2 = document.createElement('div');
          title2.innerHTML = `<span class="scareshift-muted">${sub.label||pk}</span>`;
          wrap.appendChild(title2);
          const select = document.createElement('select');
          select.className = 'scareshift-select';
          select.id = fieldId(qid)+'__'+pk;
          select.innerHTML = '<option value="">—</option>';
          Object.keys(sub.options).forEach(k=>{
            const o = document.createElement('option');
            o.value = k; o.textContent = sub.options[k];
            select.appendChild(o);
          });
          wrap.appendChild(select);
        });
      }
      formEl.appendChild(wrap);
    }

    Object.keys(cfg.ui).forEach(qid=> renderQuestion(qid, cfg.ui[qid]));

    function collectAnswers(){
      const a = {};
      Object.keys(cfg.ui).forEach(qid=>{
        const q = cfg.ui[qid];
        if(q.type==='single'){
          a[qid] = (document.getElementById(fieldId(qid))||{}).value || null;
        } else if (q.type==='multi'){
          const sel = [];
          Object.keys(q.options).forEach(k=>{
            const id = fieldId(qid)+'_'+k;
            const el = document.getElementById(id);
            if(el && el.checked) sel.push(k);
          });
          a[qid] = sel;
        } else if (q.type==='composite'){
          a[qid] = {};
          Object.keys(q.parts).forEach(pk=>{
            a[qid][pk] = (document.getElementById(fieldId(qid)+'__'+pk)||{}).value || null;
          });
        }
      });
      return a;
    }

    function scoreFromAnswers(ans){
      const s = { fear:5, agility:5, endurance:5, voice:5, presence:5 };
      // Apply weights based on cfg.questions structure from scoring-config.json
      function w(path, sel){
        const node = path.reduce((o,k)=> (o&&o[k]!=null)?o[k]:undefined, cfg.questions);
        if(!node) return;
        // node has 'weights'
        if(Array.isArray(sel)){
          sel.forEach(k=> applyWeights(s, node.weights[k]));
        } else if (sel && typeof sel === 'object' && node.parts){
          Object.keys(sel).forEach(pk=>{
            const part = node.parts[pk];
            if(part && part.weights){
              applyWeights(s, part.weights[ sel[pk] ]);
            }
          });
        } else {
          applyWeights(s, node.weights ? node.weights[sel] : null);
        }
      }

      w(['archetype'], ans.archetype);
      w(['primary_method'], ans.primary_method);
      w(['mobility'], ans.mobility);
      w(['activity_level'], ans.activity_level);
      w(['costume_restriction'], ans.costume_restriction);
      w(['scene_conditions'], ans.scene_conditions);
      w(['prop_usage'], ans.prop_usage);
      w(['vocal_type'], ans.vocal_type);
      w(['vocal_care'], ans.vocal_care);
      w(['interaction'], ans.interaction);
      w(['experience'], ans.experience);
      if(ans.training_recent){ applyWeights(s, cfg.questions.training_recent.weights_true); }
      w(['stamina_habits'], ans.stamina_habits);

      // Guardrails (simplified client side)
      if(ans.unsafe_flags && Array.isArray(ans.unsafe_flags)){
        ans.unsafe_flags.forEach(flag=> applyWeights(s, (cfg.guardrails.unsafe_flags||{})[flag]) );
      }
      // Voice strain
      if(ans.vocal_type==='scream' && (!ans.vocal_care || (ans.vocal_care.indexOf('warmup_always')===-1 && ans.vocal_care.indexOf('warmup_sometimes')===-1))){
        if(cfg.guardrails.voice_strain && cfg.guardrails.voice_strain.penalty){
          applyWeights(s, cfg.guardrails.voice_strain.penalty);
        }
      }
      // Over restriction
      if(['heavy','extreme'].includes(ans.costume_restriction) && ans.activity_level==='high'){
        if(cfg.guardrails.over_restriction && cfg.guardrails.over_restriction.penalty){
          applyWeights(s, cfg.guardrails.over_restriction.penalty);
        }
      }

      return clampAndRound(s);
    }

    function renderSheet(scores, ans){
      const sheet = document.getElementById('scareshift-sheet');
      sheet.innerHTML = `
        <div class="scareshift-stats">
          <div class="scareshift-stat"><b>Fear</b><div>${scores.fear}/10</div></div>
          <div class="scareshift-stat"><b>Agility</b><div>${scores.agility}/10</div></div>
          <div class="scareshift-stat"><b>Endurance</b><div>${scores.endurance}/10</div></div>
          <div class="scareshift-stat"><b>Voice</b><div>${scores.voice}/10</div></div>
          <div class="scareshift-stat"><b>Presence</b><div>${scores.presence}/10</div></div>
        </div>
        <div class="scareshift-muted" style="margin-top:8px">${statSummary(scores)}</div>
      `;
    }

    function update(){
      const ans = collectAnswers();
      const scores = scoreFromAnswers(ans);
      renderSheet(scores, ans);
      return {ans, scores};
    }

    // Initial render
    let current = update();
    formEl.addEventListener('change', ()=> current = update());

    // Load sample
    $('#scareshift-load-sample').on('click', ()=>{
      const s = samples.list[ sel.value ];
      if(!s) return;
      // populate fields
      Object.keys(s.answers||{}).forEach(qid=>{
        const q = cfg.ui[qid];
        if(!q) return;
        if(q.type==='single'){
          const el = document.getElementById('q_'+qid);
          if(el) el.value = s.answers[qid] || '';
        } else if(q.type==='multi'){
          Object.keys(q.options).forEach(k=>{
            const el = document.getElementById('q_'+qid+'_'+k);
            if(el) el.checked = (s.answers[qid]||[]).indexOf(k) !== -1;
          });
        } else if(q.type==='composite'){
          Object.keys(q.parts).forEach(pk=>{
            const el = document.getElementById('q_'+qid+'__'+pk);
            if(el) el.value = (s.answers[qid]||{})[pk] || '';
          });
        }
      });
      current = update();
    });

    // Export
    async function exportCanvas(){
      const node = document.getElementById('scareshift-sheet');
      const canvas = await html2canvas(node);
      return canvas;
    }
    document.getElementById('scareshift-export-pdf').addEventListener('click', async ()=>{
      try{
        const { jsPDF } = window.jspdf || {};
        const canvas = await exportCanvas();
        if(jsPDF){
          const pdf = new jsPDF({ unit:'px', format:'a4' });
          const img = canvas.toDataURL('image/png');
          const pageWidth = pdf.internal.pageSize.getWidth();
          const ratio = pageWidth / canvas.width;
          pdf.addImage(img, 'PNG', 0, 10, pageWidth, canvas.height * ratio);
          pdf.save('OHA-Character-Sheet.pdf');
        }
      } catch(e){ alert('PDF export failed.'); console.error(e); }
    });
    document.getElementById('scareshift-export-png').addEventListener('click', async ()=>{
      try{
        const canvas = await exportCanvas();
        const link = document.createElement('a');
        link.download = 'OHA-Character-Sheet.png';
        link.href = canvas.toDataURL('image/png');
        link.click();
      } catch(e){ alert('PNG export failed.'); console.error(e); }
    });

    // Save
    document.getElementById('scareshift-save').addEventListener('click', async ()=>{
      try{
        const res = await fetch(SCARESHIFT.restUrl + 'character/save', {
          method:'POST',
          headers:{ 'Content-Type':'application/json', 'X-WP-Nonce': SCARESHIFT.nonce },
          body: JSON.stringify({ answers: current.ans, scores: current.scores, xp: 0 })
        });
        const data = await res.json();
        if(data && data.ok){ document.getElementById('scareshift-save-msg').textContent = 'Saved ✔'; }
        else { document.getElementById('scareshift-save-msg').textContent = 'Could not save (are you logged in?)'; }
      } catch(e){
        document.getElementById('scareshift-save-msg').textContent = 'Save failed.';
        console.error(e);
      }
    });

    } catch(error) {
      console.error('ScareShift: Error in renderCreator:', error);
      node.innerHTML = `<div class="scareshift-notice scareshift-notice-danger">
        <strong>Error loading character creator:</strong> ${error.message}
        <br><br>
        Please check the browser console for details.
      </div>`;
    }
  } // renderCreator

  async function renderLibrary(node){
    await ensureCharterModal();

    node.innerHTML = `<div class="scareshift-card">
      <h3 class="scareshift-h">My Saved Characters</h3>
      <div id="scareshift-lib-list"></div>
    </div>`;

    async function load(){
      const res = await fetch(SCARESHIFT.restUrl + 'character/list', { headers: {'X-WP-Nonce': SCARESHIFT.nonce} });
      const data = await res.json();
      const root = document.getElementById('scareshift-lib-list');
      if(!data || !data.characters || data.characters.length===0){
        root.innerHTML = `<p class="scareshift-muted">No saved characters yet.</p>`;
        return;
      }
      root.innerHTML = '';
      data.characters.forEach((row, idx)=>{
        const div = document.createElement('div');
        div.className = 'scareshift-card';
        const scores = row.scores || {};
        const ans = row.answers || {};
        const saved = row._saved_at || '';
        div.innerHTML = `
          <div style="display:flex; justify-content:space-between; gap:10px; flex-wrap:wrap">
            <div>
              <div><b>Archetype:</b> ${(ans.archetype||'—')}</div>
              <div class="scareshift-muted">${'Fear '+(scores.fear||'—')+' · Agi '+(scores.agility||'—')+' · End '+(scores.endurance||'—')+' · Voice '+(scores.voice||'—')+' · Pres '+(scores.presence||'—')}</div>
              <div class="scareshift-muted">Saved ${saved}</div>
            </div>
            <div style="display:flex; gap:8px">
              <button class="scareshift-btn" data-idx="${idx}" data-act="dl">Download</button>
              <button class="scareshift-btn" data-idx="${idx}" data-act="del">Delete</button>
            </div>
          </div>`;
        root.appendChild(div);
      });

      root.querySelectorAll('button').forEach(btn=>{
        btn.addEventListener('click', async (e)=>{
          const idx = parseInt(e.target.getAttribute('data-idx'));
          const act = e.target.getAttribute('data-act');
          if(act==='del'){
            const row = (data.characters||[])[idx];
            if(row && row._id){
              await fetch(SCARESHIFT.restUrl + 'character/delete', {
                method:'POST',
                headers:{ 'Content-Type':'application/x-www-form-urlencoded', 'X-WP-Nonce': SCARESHIFT.nonce },
                body: new URLSearchParams({ character_id: row._id })
              });
              await load();
            }
          } else if (act==='dl'){
            try{
              const row = (data.characters||[])[idx] || {};
              const scores = row.scores || {fear:5,agility:5,endurance:5,voice:5,presence:5};
              const sheet = document.createElement('div');
              sheet.className = 'scareshift-sheet';
              sheet.style.position='fixed'; sheet.style.left='-9999px';
              sheet.innerHTML = `
                <div class="scareshift-stats">
                  <div class="scareshift-stat"><b>Fear</b><div>${scores.fear}/10</div></div>
                  <div class="scareshift-stat"><b>Agility</b><div>${scores.agility}/10</div></div>
                  <div class="scareshift-stat"><b>Endurance</b><div>${scores.endurance}/10</div></div>
                  <div class="scareshift-stat"><b>Voice</b><div>${scores.voice}/10</div></div>
                  <div class="scareshift-stat"><b>Presence</b><div>${scores.presence}/10</div></div>
                </div>`;
              document.body.appendChild(sheet);
              const canvas = await html2canvas(sheet);
              const link = document.createElement('a');
              link.download = 'OHA-Character-Sheet.png';
              link.href = canvas.toDataURL('image/png');
              link.click();
              sheet.remove();
            } catch(err){ alert('Export failed'); console.error(err); }
          }
        });
      });
    }
    await load();
  }

  document.addEventListener('DOMContentLoaded', ()=>{
    console.log('ScareShift: DOMContentLoaded fired');
    const creatorNode = document.getElementById('scareshift-creator');
    const libraryNode = document.getElementById('scareshift-library');
    
    console.log('ScareShift: Creator node:', creatorNode);
    console.log('ScareShift: Library node:', libraryNode);
    
    if(creatorNode && creatorNode.dataset.mode === 'creator') {
      console.log('ScareShift: Calling renderCreator');
      renderCreator(creatorNode);
    }
    if(libraryNode && libraryNode.dataset.mode === 'library') {
      console.log('ScareShift: Calling renderLibrary');
      renderLibrary(libraryNode);
    }
  });
})(jQuery);
