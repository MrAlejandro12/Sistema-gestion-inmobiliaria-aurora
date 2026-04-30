  <!-- CONTENT ENDS -->
  </div><!-- .page -->
</div><!-- .main -->

<div id="toast-wrap"></div>

<?php if (!empty($flashMsg)): ?>
<script>
document.addEventListener('DOMContentLoaded',()=>showToast(<?= json_encode($flashMsg['msg']) ?>, <?= json_encode($flashMsg['type'] ?? '') ?>));
</script>
<?php endif; ?>

<script>
// ── TOAST ────────────────────────────────────────────────────
function showToast(msg, type=''){
  const wrap = document.getElementById('toast-wrap');
  const t = document.createElement('div');
  t.className = 'toast' + (type ? ' '+type : '');
  const icons = {ok:'✅', err:'❌', warn:'⚠️', '':'ℹ️'};
  t.innerHTML = `<span>${icons[type]||'ℹ️'}</span><span>${msg}</span>`;
  wrap.appendChild(t);
  setTimeout(()=>{ t.style.transition='all .3s'; t.style.opacity='0'; t.style.transform='translateX(20px)'; setTimeout(()=>t.remove(),300); }, 3500);
}

// ── MODAL ────────────────────────────────────────────────────
function openModal(id){ document.getElementById(id).classList.add('open'); }
function closeModal(id){ document.getElementById(id).classList.remove('open'); }
document.addEventListener('click', e=>{ if(e.target.classList.contains('modal-bg')) e.target.classList.remove('open'); });

// ── CONFIRM DELETE ────────────────────────────────────────────
function confirmDelete(url, msg){
  if(confirm(msg||'¿Confirmar eliminación?')){
    fetch(url, {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'_method=DELETE'})
      .then(r=>r.json()).then(d=>{ showToast(d.message||'Eliminado', d.ok?'ok':'err'); if(d.ok) setTimeout(()=>location.reload(),800); })
      .catch(()=>showToast('Error al eliminar','err'));
  }
}

// ── AJAX FORM ─────────────────────────────────────────────────
function submitForm(formId, successMsg){
  const form = document.getElementById(formId);
  if(!form) return;
  form.addEventListener('submit', async e=>{
    e.preventDefault();
    const btn = form.querySelector('[type=submit]');
    if(btn){ btn.disabled=true; btn.textContent='Guardando...'; }
    try {
      const res  = await fetch(form.action||window.location.href, {method:'POST', body:new FormData(form)});
      const data = await res.json();
      if(data.ok){
        showToast(successMsg||data.message||'Guardado', 'ok');
        if(data.reload) setTimeout(()=>location.reload(), 900);
        if(data.redirect) setTimeout(()=>window.location.href=data.redirect, 900);
        const modalId = form.closest('.modal-bg')?.id;
        if(modalId) closeModal(modalId);
      } else {
        showToast(data.message||'Error al guardar', 'err');
      }
    } catch(err) {
      showToast('Error de conexión', 'err');
    } finally {
      if(btn){ btn.disabled=false; btn.textContent=btn.dataset.label||'Guardar'; }
    }
  });
}
</script>

<!-- CSRF token for AJAX calls -->
<meta name="csrf-token" id="csrf-meta" content="">
<script>
// Set CSRF meta (filled server-side via PHP)
(function(){
  const meta = document.getElementById('csrf-meta');
  if(meta) meta.content = '<?= csrf_token() ?>';
})();

// Wrapper: agrega CSRF a todos los fetch POST automáticamente
const _origFetch = window.fetch;
window.fetch = function(url, opts={}) {
  if(opts.method && opts.method.toUpperCase() === 'POST') {
    const token = document.getElementById('csrf-meta')?.content || '';
    if(opts.body instanceof FormData) {
      opts.body.append('_csrf_token', token);
    } else if(opts.body && typeof opts.body === 'string' && opts.body.includes('=')) {
      opts.body += '&_csrf_token=' + encodeURIComponent(token);
    }
    opts.headers = Object.assign({'X-CSRF-Token': token}, opts.headers||{});
  }
  return _origFetch(url, opts);
};
</script>

</body>
</html>
