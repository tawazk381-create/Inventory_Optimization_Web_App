// File: public/assets/js/form-validation.js
// Bootstrap 4/5 client-side validation helper
(function(){
  'use strict';

  window.addEventListener('load', function() {
    var forms = document.getElementsByClassName('needs-validation');
    Array.prototype.forEach.call(forms, function(form){
      form.addEventListener('submit', function(event){
        if(form.checkValidity() === false) {
          event.preventDefault();
          event.stopPropagation();
        }
        form.classList.add('was-validated');
      }, false);
    });
  }, false);
})();
