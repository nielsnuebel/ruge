(function($){
    $(document).ready(function() {
        $(".menu-trigger").click(function () {
            $(this).toggleClass("active"), $("#mainmenu").toggleClass("active"), $("header").removeClass("pinned not-top").toggleClass("openNavigation"), $("html,body").toggleClass("openNavigation"), $("nav").hasClass("active") && $(document).keydown(function (e) {
                27 === e.keyCode && (e.preventDefault(), $(".menu-trigger").click())
            })
        });

        /*============================================
        Resize Functions
        ==============================================*/
        $(window).resize(function(){
            scrollSpyRefresh();
            waypointsRefresh();
        });
        /*============================================
         Refresh scrollSpy function
         ==============================================*/
        function scrollSpyRefresh(){
            setTimeout(function(){
                var offset = $('header').outerHeight();
                $('body').scrollspy('refresh');
                $('body').scrollspy({
                    offset: offset+5
                });
            },1000);
        }

        /*============================================
         Refresh waypoints function
         ==============================================*/
        function waypointsRefresh(){
            setTimeout(function(){
                $.waypoints('refresh');
            },1000);
        }

        $(window).resize(function() {
            header = $('header').outerHeight();
        }).trigger("resize");

        $('body').scrollspy({
            offset: header+5
        });

        $(document).on("scroll", function() {
            var p = $(window).scrollTop();
            if(p > header) {
                $("header").addClass("shadow")
            }
            if(p == 0){
                $("header").removeClass("shadow")
            }
        }).trigger("scroll");

        /*============================================
         ScrollTo Links
         ==============================================*/
        $('a.scrollto').click(function(e){
            $('html,body').scrollTo(this.hash, this.hash, {gap:{y:-header},animation:  {easing: 'easeInOutCubic', duration: 1600}});
            e.preventDefault();

            if(!$(this).parent().hasClass('scrolldown')){
                $("#mainmenu,.menu-trigger").toggleClass("active");
            }
        });


    });
})(this.jQuery);

