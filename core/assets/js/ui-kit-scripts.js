( function( $ )
{
  $(document).ready(function() {
    // uikit menu
    var titles = $('main > .ui-section .ui-title');
    var ui_menu = $('#ui-menu');
    var ui_menu_content = '';
    var title;
    var toggle_menu = $('#show-menu');

    titles.each(function(i, e){
      ui_menu_content += '<li data-id="'+i+'">' + (i+1) + '. ' + $(e).text() + '</li>';
    });

    ui_menu.append(ui_menu_content);

    title = ui_menu.find('li');

    title.on('click', function(){
      var thiz = $(this);
      var thizSection = thiz.data('id');

      title.removeClass('is-active');
      thiz.addClass('is-active');

      $('html, body').animate({scrollTop: $('#sections > li').eq(thizSection).offset().top});
    });

    $('.ui-toggle-menu').on('click', function(){
      ui_menu.toggleClass('is-active');
      title.removeClass('is-active');
    });
  });
} )( jQuery );