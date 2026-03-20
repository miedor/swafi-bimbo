document.addEventListener('DOMContentLoaded',()=>{
  document.querySelectorAll('[data-demo-nav]').forEach(btn=>{
    btn.addEventListener('click',e=>{ e.preventDefault(); const to=btn.getAttribute('href'); if(to) window.location.href=to; });
  });
});
