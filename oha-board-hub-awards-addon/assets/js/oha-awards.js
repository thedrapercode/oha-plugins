(function(){
  document.addEventListener('click', function(e){
    var t = e.target;
    if (t && t.matches('.oa-compact-toggle')) {
      e.preventDefault();
      document.body.classList.toggle('oha-compact');
    }
  });

  // AJAX status updates
  document.addEventListener('submit', async function(e){
    var form = e.target;
    if (form && form.classList.contains('oha-status-form')) {
      e.preventDefault();
      var fd = new FormData(form);
      try {
        fd.append('action', 'oha_awards_front_set_status');
        const resp = await fetch(form.dataset.posturl || (window.ohaAwards?.postUrl) || (window.ajaxurl || '/wp-admin/admin-post.php'), {
          method: 'POST',
          credentials: 'same-origin',
          body: fd
        });
        // On success, we can optimistically show a tick
        if (resp.ok) {
          var btn = form.querySelector('button');
          if (btn) {
            var original = btn.textContent;
            btn.disabled = true;
            btn.textContent = 'Saved';
            setTimeout(function(){
              btn.disabled = false;
              btn.textContent = original;
            }, 1200);
          }
        } else {
          alert('Save failed. Please refresh and try again.');
        }
      } catch (err) {
        alert('Network error. Please refresh and try again.');
      }
    }
  }, true);
})();

  // Haunt search
  function ohaDebounce(fn, ms){ var t; return function(){ clearTimeout(t); var a=arguments,c=this; t=setTimeout(function(){ fn.apply(c,a); }, ms); }; }
  function ohaRenderResults(container, items){
    container.innerHTML = '';
    var wrap = document.createElement('div');
    wrap.className = 'oha-haunt-dropdown';
    items.forEach(function(it){
      var a = document.createElement('div'); a.textContent = it.text; a.setAttribute('data-id', it.id);
      a.style.padding = '8px'; a.style.cursor = 'pointer';
      a.addEventListener('click', function(){
        var h = document.getElementById('oha-haunt-id'); var i = document.getElementById('oha-haunt-search');
        if (h) h.value = it.id; if (i) i.value = it.text; container.innerHTML = '';
        var cb = document.getElementById('oha-haunt-not-listed'); var free = document.getElementById('oha-haunt-free');
        if (cb && free){ cb.checked = false; free.style.display='none'; }
      });
      wrap.appendChild(a);
    });
    container.appendChild(wrap);
  }
  document.addEventListener('DOMContentLoaded', function(){
    var input = document.getElementById('oha-haunt-search');
    var results = document.getElementById('oha-haunt-results');
    var hidden = document.getElementById('oha-haunt-id');
    if (input && results && hidden){
      if (typeof window.ohaAwardsDefaultHaunt !== 'undefined' && window.ohaAwardsDefaultHaunt.id){
        hidden.value = window.ohaAwardsDefaultHaunt.id;
        input.value = window.ohaAwardsDefaultHaunt.text || '';
      }
      input.addEventListener('input', ohaDebounce(async function(){
        var q = input.value.trim(); hidden.value=''; if (!q){ results.innerHTML=''; return; }
        var url = (window.ohaAwards && ohaAwards.ajaxUrl ? ohaAwards.ajaxUrl : '/wp-admin/admin-ajax.php')
          + '?action=oha_awards_search_haunts&nonce=' + encodeURIComponent(ohaAwards.nonce) + '&q=' + encodeURIComponent(q);
        try{ var res = await fetch(url, {credentials:'same-origin'});
             var data = await res.json();
             if (data && data.success){ ohaRenderResults(results, data.data.results || []); } else { results.innerHTML=''; } }
        catch(e){ results.innerHTML=''; }
      }, 250));
    }
    var cb = document.getElementById('oha-haunt-not-listed');
    var free = document.getElementById('oha-haunt-free');
    if (cb && free){
      cb.addEventListener('change', function(){
        free.style.display = cb.checked ? 'block' : 'none';
        if (cb.checked){ if (hidden) hidden.value=''; if (input) input.value=''; if (results) results.innerHTML=''; }
      });
    }
  });
