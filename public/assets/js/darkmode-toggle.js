// File: public/assets/js/darkmode-toggle.js
(function(){
  try {
    const toggle = document.getElementById('darkToggle');
    if (!toggle) return;
    toggle.addEventListener('click', function(){
      document.body.classList.toggle('dark-mode');
      localStorage.setItem('dark', document.body.classList.contains('dark-mode'));
    });
    if (localStorage.getItem('dark') === 'true') document.body.classList.add('dark-mode');
  } catch(e) { console.error(e); }
})();
