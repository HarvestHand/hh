<?php header('HTTP/1.1 500 Internal Server Error'); ?>

<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <!--[if IE]><![endif]-->
        <title>HarvestHand / Smoked!</title>
        <meta name="description" content="">
        <meta name="author" content="">
        <link rel="shortcut icon" href="/_images/favicon.png">
        <link rel="apple-touch-icon" href="/images/apple-touch-icon.png">

        <link rel="stylesheet" href="/_css/style.css?v=2">
        <link rel="stylesheet" media="handheld" href="/_css/handheld.css?v=1">
        <script src="/_js/modernizr.js"></script>
    </head>
    <!--[if lt IE 7 ]> <body class="ie6"> <![endif]-->
    <!--[if IE 7 ]>    <body class="ie7"> <![endif]-->
    <!--[if IE 8 ]>    <body class="ie8"> <![endif]-->
    <!--[if IE 9 ]>    <body class="ie9"> <![endif]-->

    <!--[if (gt IE 9)|!(IE)]><!--> <body> <!--<![endif]-->
        <header id="top">
            <div id="header" class="container_12">
                <div class="grid_12">
                    <div class="grid_3 alpha">
                        <a href="/"><img id="logo" src="/_images/logo.png" width="176" height="40" alt="HarvestHand - Local Farms &amp; Communities" /></a>
                    </div>

                    <nav id="nav-main" class="grid_9 omega">
                    </nav>
                </div>

            </div>
        </header>

        <section id="body">
            <div id="body-header" class="container_12">
                <h1 class="grid_12">
                    HarvestHand
                </h1>
            </div>
            <section id="body-content" class="container_12">

                <div id="body-content-left" class="grid_12 ">
                    <div id="body-content-right">
                        <div id="body-content-content">
                            <h2>Something Wrong!</h2>

                            <p>Well, this is embarrassing.  Something went boom.  We've recorded what's happened and have just dispatched the clean up crew.</p>

                            <figure style="display: block; width: 384px; margin: 0 auto; margin-top: 20px;">
                                <img src="/_images/500.png" alt="500" width="384" height="341" />
                            </figure>

                            <?php if (Bootstrap::$env == 'development') { ?>
                                <?php Zend_Debug::dump($error); ?>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            </section>
            <br class="clear"/>
        </section>

        <footer id="footer">
            <div class="container_12">
                <div class="grid_12">
                    <div id="footer-company" class="grid_4 alpha">

                        <h3>Company</h3>

                        <ul>
                            <li><a href="http://www.mompopmedia.com/">About</a></li>

                            <li><a href="#">Privacy</a></li>
                        </ul>
                    </div>
                    <div id="footer-contact" class="grid_4">
                        <h3>Stay in Touch</h3>

                        <ul>
                            <li><a href="mailto:farmnik@harvesthand.com" title="Email"><span class="icon-social icon-social-email"></span></a></li>

                            <li><a href="#" title="RSS"><span class="icon-social icon-social-rss"></span></a></li>
                            <li><a href="http://www.facebook.com/pages/HarvestHand/143523832349725" title="Facebook"><span class="icon-social icon-social-facebook"></span></a></li>
                            <li><a href="http://twitter.com/HarvestHand" title="Twitter"><span class="icon-social icon-social-twitter"></span></a></li>
                        </ul>
                    </div>
                    <div id="footer-support" class="grid_4 omega">
                        <h3>Support</h3>

                        <ul>

                            <li><a href="#">Get Help</a></li>
                            <li><a href="/forum">Forum</a></li>
                            <li><a href="#">Create Support Ticket</a></li>
                        </ul>
                    </div>
                </div>
            </div>

            <br class="clear"/>
        </footer>

        <script src="http://ajax.googleapis.com/ajax/libs/jquery/1.4.2/jquery.min.js"></script>
        <script>!window.jQuery && document.write('<script src="/js/jquery-1.4.2.min.js"><\/script>')</script>
        <script src="http://ajax.googleapis.com/ajax/libs/jqueryui/1.8.5/jquery-ui.min.js"></script>
        <script>!$.ui && document.write('<script src="/js/jquery-ui-1.8.5.custom.min.js"><\/script>')</script>

        <script type="text/javascript">
        document.write(unescape("%3Cscript src='/_stats/piwik.js' type='text/javascript'%3E%3C/script%3E"));
        </script><script type="text/javascript">
        try {
        var piwikTracker = Piwik.getTracker("/_stats/piwik.php", 1);
        piwikTracker.trackPageView();
        piwikTracker.enableLinkTracking();
        } catch( err ) {}
        </script><noscript><p><img src="/_stats/piwik.php?idsite=1" style="border:0" alt="" /></p></noscript>
    </body>
</html>