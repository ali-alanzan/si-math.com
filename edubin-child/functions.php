<?php
// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

// BEGIN ENQUEUE PARENT ACTION
// AUTO GENERATED - Do not modify or remove comment markers above or below:

if ( !function_exists( 'chld_thm_cfg_locale_css' ) ):
    function chld_thm_cfg_locale_css( $uri ){
        if ( empty( $uri ) && is_rtl() && file_exists( get_template_directory() . '/rtl.css' ) )
            $uri = get_template_directory_uri() . '/rtl.css';
        return $uri;
    }
endif;
add_filter( 'locale_stylesheet_uri', 'chld_thm_cfg_locale_css' );

// END ENQUEUE PARENT ACTION

























add_action('wp_footer', 'add_fullpage_desmos_board');

function add_fullpage_desmos_board() {
    // Only show on Tutor LMS Lesson, Quiz, or Course pages
    if ( function_exists('tutor_utils') && (is_singular('lesson') || is_singular('courses') || is_singular('tutor_quiz')) ) {
        ?>
        <button id="desmos-toggle-btn" onclick="toggleDesmos()">
            <span style="font-size:18px;">📐</span> رسم بياني
        </button>

        <div id="desmos-container">
            <div id="desmos-header">
                <span style="font-weight:bold; font-size: 18px;">لوحة رسم بياني</span>
                <button onclick="toggleDesmos()" id="desmos-close-x">✕ إغلاق</button>
            </div>
            
            <iframe id="desmos-iframe" 
                src="about:blank" 
                allow="clipboard-write"
                style="width: 100%; height: calc(100vh - 60px); border: none;">
            </iframe>
        </div>

        <style>
            /* The Button */
            #desmos-toggle-btn {
                position: fixed;
                bottom: 25px;
                right: 50%;
                z-index: 999999;
                padding: 14px 22px;
                background: #3057d5;
                color: white;
                border: none;
                border-radius: 8px;
                cursor: pointer;
                box-shadow: 0 4px 12px rgba(0,0,0,0.3);
                font-family: 'Segoe UI', Roboto, sans-serif;
                font-weight: 600;
                transition: transform 0.2s;
            }
            #desmos-toggle-btn:hover { transform: scale(1.05); }

            /* The Full Page Overlay */
            #desmos-container {
                display: none;
                position: fixed;
                top: 34px;
                right: 5vw;
                width: calc(100vw - 10vw);
                height: 92vh;
                z-index: 1000000;
                background: white;
                border: 2px solid #3057d5;
                overflow: hidden;
                border-radius: 10px 10px 0 10px;
            }

            /* The Top Bar */
            #desmos-header {
                background: #222;
                color: white;
                height: 60px;
                padding: 0 25px;
                display: flex;
                justify-content: space-between;
                align-items: center;
                font-family: sans-serif;
                box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            }

            #desmos-close-x {
                background: #e74c3c;
                border: none;
                color: white;
                padding: 8px 16px;
                border-radius: 4px;
                cursor: pointer;
                font-weight: bold;
                transition: background 0.2s;
            }
            #desmos-close-x:hover {
                background: #c0392b;
            }

            /* Prevent scrolling of the background site when board is open */
            .desmos-open {
                overflow: hidden !important;
            }
        </style>

        <script>
            function toggleDesmos() {
                var container = document.getElementById("desmos-container");
                var iframe = document.getElementById("desmos-iframe");
                var body = document.body;
                
                if (container.style.display === "block") {
                    container.style.display = "none";
                    body.classList.remove("desmos-open"); // Allow site scrolling again
                } else {
                    container.style.display = "block";
                    body.classList.add("desmos-open"); // Lock site scrolling
                    
                    // Load the full interactive calculator if not already loaded
                    if (iframe.src === "about:blank") {
                        iframe.src = "https://www.desmos.com/calculator";
                    }
                }
            }
        </script>
        <?php
    }
}



