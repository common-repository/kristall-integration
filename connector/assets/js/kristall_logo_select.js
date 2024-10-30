jQuery(document).ready(function($) {
  $('.headerLogo_upload').click(function(e) {
    e.preventDefault();

    var custom_uploader = wp.media({
      title: 'Загрузка логотипа',
      button: {
        text: 'Загрузить'
      },
      multiple: false  // Set this to true to allow multiple files to be selected
    })
    .on('select', function() {
      var attachment = custom_uploader.state().get('selection').first().toJSON();
      $('.headerLogo').attr('src', attachment.url);
      $('.headerLogo_url').val(attachment.url);
    })
    .open();
  });
});
