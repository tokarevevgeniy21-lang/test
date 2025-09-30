// Clean main JS for ON Service CRM
(function(){
  document.addEventListener('DOMContentLoaded', function() {
    var csrfMeta = document.querySelector('meta[name="csrf-token"]');
    var csrfToken = csrfMeta ? csrfMeta.content : null;
    var csrfHeader = 'X-CSRF-Token';

    if (typeof axios !== 'undefined' && csrfToken) {
      axios.defaults.headers.common[csrfHeader] = csrfToken;
      axios.interceptors.request.use(function(req){ req.headers[csrfHeader] = csrfToken; return req; });
    }

    document.querySelectorAll('.nav-link').forEach(function(el){
      el.addEventListener('click', function(){
        document.querySelectorAll('a.active').forEach(function(a){ a.classList.remove('active'); });
        el.classList.add('active');
      });
    });

    var search = document.getElementById('global-search');
    if (search) search.addEventListener('keyup', function(){});

    document.querySelectorAll('.modal-close').forEach(function(btn){ btn.addEventListener('click', function(){ var m = btn.closest('.modal'); if (m) m.style.display='none'; }); });

    document.querySelectorAll('.btn-print').forEach(function(btn){
      btn.addEventListener('click', function(){
        var preview = btn.closest('.modal-preview'); if (!preview) return;
        var win = window.open('', '', 'width=800,height=600');
        win.document.write('<!doctype html><html><head><meta charset="utf-8"><title>Print</title></head><body>' + preview.innerHTML + '</body></html>');
        win.document.close();
      });
    });
  });
})();
