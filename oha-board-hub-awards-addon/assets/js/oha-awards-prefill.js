(function(){document.addEventListener('DOMContentLoaded',function(){
  if (typeof ohaAwardsPrefill !== 'object') return;
  var data = ohaAwardsPrefill || {};
  var title = document.querySelector('input[name="post_title"],#post_title,input[name="listing_title"],input[name="geodir_title"]');
  if (title && data.haunt_name){ try{ title.value = data.haunt_name; title.dispatchEvent(new Event('input',{bubbles:true})); }catch(e){} }
  var desc = document.querySelector('textarea[name="post_content"],#content,textarea[name="listing_description"],textarea[name^="geodir"]');
  if (desc && data.reason){ try{ desc.value = (data.actor_name ? (data.actor_name + '\n') : '') + data.reason; }catch(e){} }
  try{ fetch((window.ohaAwards && ohaAwards.ajaxUrl ? ohaAwards.ajaxUrl : '/wp-admin/admin-ajax.php'), {method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'action=oha_awards_clear_prefill&_wpnonce=' + encodeURIComponent((window.ohaAwards && ohaAwards.nonce) ? ohaAwards.nonce : '')}); }catch(e){}
});})();