//Handmade with love by http://esmes.fi

var App = function(options){
  'use strict';
  var a = this;

  var defaults = {
    load_treshold_percentage: 300
  };

  a.config = $.extend(true, {}, defaults, options);

  a.handleScroll = function(){
    var scroll = $(window).scrollTop();
    a.scroll = scroll;
  };

  a.renderImage = function(image){
    return '<div><img src="'+image.thumb+'"/></div>';
  };


  a.init = function(){
    if($('#upload').length) $('#upload')[0].reset();

    window.addEventListener('scroll', throttle(a.handleScroll, 15));
    $(window).scroll(a.handleScroll);

    var html = '';
    log(window.data);
    $.each(window.data, function(i, image){
      html += a.renderImage(image);
    });

    $('.root').append(html);

    var hash_accepted = false;

    if(window.localStorage && window.localStorage.getItem('hash')){
      $.ajax('?api', {
        type: 'POST',
        cache: false,
        dataType: 'json',
        data: {
          a: 'checkHash',
          hash: window.localStorage.getItem('hash')
        },
        success: function(data){
          if(typeof data.status != "undefined" && data.status == "OK"){
            hash_accepted = true;
            $('#upload [name="password"]').hide();
          }
        },
        complete: function(){
          $('#upload').show();
        }
      });
    }

    $('[role="upload-close"]').on('click', function(){
      $('#upload').fadeOut(200);
    });

    $('#upload').on('submit', function(e){
      e.preventDefault();

      $('#upload [type="submit"]').prop('disabled', true);

      var formdata = new FormData();
      formdata.append('a', 'uploadFile');
      formdata.append('file', $('#upload [name="file"]')[0].files[0]);

      if(hash_accepted){
        formdata.append('hash', window.localStorage.getItem('hash'));
      } else {
        formdata.append('password', $('#upload [name="password"]').val());
      }

      $.ajax('?api', {
        type: 'POST',
        cache: false,
        data: formdata,
        dataType: 'json',
        processData: false,
        contentType: false,
        enctype: 'multipart/form-data',
        success: function(data){
          log(data);
          if(data && data.status && data.status == "OK"){
            if(typeof window.localStorage !== "undefined" && data.hash){
              window.localStorage.setItem('hash', data.hash);
            }
            setTimeout(function(){ window.location.reload(); }, 50);
          } else {
            $('#upload [type="submit"]').prop('disabled', false);
            alert('Upload error');
          }
        },
        error: function(){
          $('#upload [type="submit"]').prop('disabled', false);
          alert('Upload error');
        }
      });
    });

  };

  a.init();
};

var app;

$(document).ready(function(){
  app = new App();
});

function throttle(fn, wait) {
  var time = Date.now();
  return function(){
    if ((time + wait - Date.now()) < 0) {
      fn();
      time = Date.now();
    }
  };
}

function log(obj) {
  if (window.debug && typeof(console) !== 'undefined' && console.log) {
    console.log(obj);
  }
}