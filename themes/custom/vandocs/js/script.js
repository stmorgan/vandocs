$( '<img id="hamburger" src="https://c9test-stmorgan.c9users.io/themes/custom/vandocs/images/hamburger.png" alt="hamburger" />' ).insertAfter( "#block-vandocs-branding" );
$('#hamburger').on('click', function(){
           $('#block-vandocs-main-menu ul').toggleClass('showing'); 
        });