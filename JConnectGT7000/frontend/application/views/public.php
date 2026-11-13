<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <!-- This file has been downloaded from Bootsnipp.com. Enjoy! -->
    <title>JCONNECT</title>
    <!-- Favicon-->
    <!--<link rel="apple-touch-icon" sizes="128x128" href="<?php echo iaBase(); ?>assets/images/nt_01.png">-->
    <!--<link rel="icon" href="<?php echo iaBase(); ?>assets/images/nt_01.png" type="image/x-icon"> -->

    <link href="<?php echo iaBase(); ?>assets/plugins/particles/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" media="screen" href="<?php echo iaBase(); ?>assets/plugins/particles/css/style.css">
    <script src="<?php echo iaBase(); ?>assets/plugins/particles/js/jquery-1.11.1.min.js"></script>
    <script src="<?php echo iaBase(); ?>assets/plugins/particles/js/bootstrap.js"></script>

</head>

<body>
    <!-- Top navigation -->
    <nav class="navbar navbar-expand-md fixed-top top-nav">
        <div class="container">
            <!--<a class="navbar-brand" href="#"><img src="<?php echo iaBase(); ?>assets/images/nt_01/04_NT-Logo-with-Full-Name.png" height=50px> <strong></strong></a> -->
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"><i class="fa fa-bars" aria-hidden="true"></i></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarSupportedContent">
                <ul class="navbar-nav ml-auto">
                    <li class="nav-item active">
                        <a class="nav-link" href="#">Home<span class="sr-only">(current)</span></a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo iaBase(); ?>user/login">Login</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Intro Seven -->
    <section class="intro carousel  bg-overlay-light h-auto" id="Header">
        <div id="intro-bgs"></div>

        <div class="carousel-caption">
            <!--<a class="navbar-brand" href="#"><img src="<?php echo iaBase(); ?>assets/images/nt_01/02_NT-Logo-with-English-Name.png" height="30px"> </a>-->
            <h1 class="display-4 text-white mb-2 mt-4">CONNECT PLUS</h1>
            <h1 class="typewrite" data-period="2000" data-type='["Smart Monitoring","Infrastructure Monitoring"]'>
                <span class="wrap"></span>
            </h1>
        </div>



    </section>

    <script src="<?php echo iaBase(); ?>assets/plugins/particles/js/particles.js"></script>
    <script src="<?php echo iaBase(); ?>assets/plugins/particles/js/app.js"></script>


</body>

<script type="text/javascript">
    // Sticky Header
    $(window).scroll(function() {
        var scroll = $(window).scrollTop();

        if (scroll >= 100) {
            $(".top-nav").addClass("light-header");
        } else {
            $(".top-nav").removeClass("light-header");
        }
    });

    // Year for copy content
    $(function() {
        var theYear = new Date().getFullYear();
        $('#year').html(theYear);
    });
</script>


<script type="text/javascript">
    $(document).ready(function() {
        particlesJS("intro-bgs", {
            "particles": {
                "number": {
                    "value": 80,
                    "density": {
                        "enable": true,
                        "value_area": 1000
                    }
                },
                "color": {
                    "value": "#ffffff"
                },
                "shape": {
                    "type": "circle",
                    "stroke": {
                        "width": 0,
                        "color": "#000000"
                    },
                    "polygon": {
                        "nb_sides": 5
                    }
                },
                "opacity": {
                    "value": 0.5,
                    "random": false,
                    "anim": {
                        "enable": false,
                        "speed": 1,
                        "opacity_min": 0.1,
                        "sync": false
                    }
                },
                "size": {
                    "value": 3,
                    "random": true,
                    "anim": {
                        "enable": false,
                        "speed": 40,
                        "size_min": 0.1,
                        "sync": false
                    }
                },
                "line_linked": {
                    "enable": true,
                    "distance": 150,
                    "color": "#ffffff",
                    "opacity": 0.4,
                    "width": 1
                },
                "move": {
                    "enable": true,
                    "speed": 6,
                    "direction": "none",
                    "random": false,
                    "straight": false,
                    "out_mode": "out",
                    "bounce": false,
                    "attract": {
                        "enable": false,
                        "rotateX": 600,
                        "rotateY": 1200
                    }
                }
            },
            "interactivity": {
                "detect_on": "canvas",
                "events": {
                    "onhover": {
                        "enable": true,
                        "mode": "grab"
                    },
                    "onclick": {
                        "enable": true,
                        "mode": "push"
                    },
                    "resize": true
                },
                "modes": {
                    "grab": {
                        "distance": 400,
                        "line_linked": {
                            "opacity": 1
                        }
                    },
                    "bubble": {
                        "distance": 400,
                        "size": 40,
                        "duration": 2,
                        "opacity": 8,
                        "speed": 3
                    },
                    "repulse": {
                        "distance": 200,
                        "duration": 0.4
                    },
                    "push": {
                        "particles_nb": 4
                    },
                    "remove": {
                        "particles_nb": 2
                    }
                }
            },
            "retina_detect": true
        });




        var TxtType = function(el, toRotate, period) {
            this.toRotate = toRotate;
            this.el = el;
            this.loopNum = 0;
            this.period = parseInt(period, 10) || 2000;
            this.txt = '';
            this.tick();
            this.isDeleting = false;
        };

        TxtType.prototype.tick = function() {
            var i = this.loopNum % this.toRotate.length;
            var fullTxt = this.toRotate[i];

            if (this.isDeleting) {
                this.txt = fullTxt.substring(0, this.txt.length - 1);
            } else {
                this.txt = fullTxt.substring(0, this.txt.length + 1);
            }

            this.el.innerHTML = '<span class="wrap">' + this.txt + '</span>';

            var that = this;
            var delta = 200 - Math.random() * 100;

            if (this.isDeleting) {
                delta /= 2;
            }

            if (!this.isDeleting && this.txt === fullTxt) {
                delta = this.period;
                this.isDeleting = true;
            } else if (this.isDeleting && this.txt === '') {
                this.isDeleting = false;
                this.loopNum++;
                delta = 500;
            }

            setTimeout(function() {
                that.tick();
            }, delta);
        };
    });
    window.onload = function() {
        var elements = document.getElementsByClassName('typewrite');
        for (var i = 0; i < elements.length; i++) {
            var toRotate = elements[i].getAttribute('data-type');
            var period = elements[i].getAttribute('data-period');
            if (toRotate) {
                new TxtType(elements[i], JSON.parse(toRotate), period);
            }
        }
        // INJECT CSS
        var css = document.createElement("style");
        css.type = "text/css";
        css.innerHTML = ".typewrite > .wrap { border-right: 0.08em }";
        document.body.appendChild(css);
    };
</script>
<style>
    .intro {
        background-color: #ff0000;
    }
</style>

</html>