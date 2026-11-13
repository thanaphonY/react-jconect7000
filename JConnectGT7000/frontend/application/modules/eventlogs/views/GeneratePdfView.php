<!DOCTYPE html>
<html>

<head>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta charset="utf-8">
    <title>Create PDF from JConnect</title>
    <link href="<?php echo iaBase(); ?>assets/fonts/th/thsarabunnew.css" rel="stylesheet" charset="utf-8" />

    <style>
        @page {
            margin-top: 0cm;
            margin-left: 1cm;
            margin-right: 1cm;
            margin-bottom: 1.5cm;
        }
/*
        .page-header,
        .page-content,
        .page-footer {
            padding: 0px;
            background-color: transparent;
            position: fixed;
            width: 100%;
      
        }*/

        .page-header {

            top: 0px;
        }

        .page-content {
            margin-top: 0px;
        }

        .page-footer {

            bottom: 0;
        }


        html {
            background-color: #b0b0b0;
            width: 210mm;
        }

        .pdfbody {

            background-color: #ffffff;
            margin: 0mm;
            margin-top: 1.3cm;
            margin-bottom: 1.5cm;
            padding: 0mm;
            font-family: 'THSarabunNew', sans-serif, Arial, Verdana, "Trebuchet MS", "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol";
            font-size: 12px;

            /* Text color */
            color: #333;

            /* Remove the background color to make it transparent. */
            background-color: #fff;

        }



        .cke_editable {
            font-size: 12px;
            line-height: 1.6;

            /* Fix for missing scrollbars with RTL texts. (#10488) */
            word-wrap: break-word;
        }

        blockquote {
            font-style: italic;
            font-family: Georgia, Times, "Times New Roman", serif;
            padding: 2px 0;
            border-style: solid;
            border-color: #ccc;
            border-width: 0;
        }

        .cke_contents_ltr blockquote {
            padding-left: 20px;
            padding-right: 8px;
            border-left-width: 5px;
        }

        .cke_contents_rtl blockquote {
            padding-left: 8px;
            padding-right: 20px;
            border-right-width: 5px;
        }

        a {
            color: #0782C1;
        }

        ol,
        ul,
        dl {
            /* IE7: reset rtl list margin. (#7334) */
            margin-right: 0px;
            /* Preserved spaces for list items with text direction different than the list. (#6249,#8049)*/
            padding: 0 40px;
        }

        h1,
        h2,
        h3,
        h4,
        h5,
        h6 {
            font-weight: normal;
            line-height: 1.2;
        }

        hr {
            border: 0px;
            border-top: 1px solid #ccc;
        }
/*
        img.right {
            border: 1px solid #ccc;
            float: right;
            margin-left: 15px;
            padding: 5px;
        }

        img.left {
            border: 1px solid #ccc;
            float: left;
            margin-right: 15px;
            padding: 5px;
        }
*/
        pre {
            white-space: pre-wrap;
            /* CSS 2.1 */
            word-wrap: break-word;
            /* IE7 */
            -moz-tab-size: 4;
            tab-size: 4;
        }

        .marker {
            background-color: Yellow;
        }

        span[lang] {
            font-style: italic;
        }

        figure {
            text-align: center;
            outline: solid 1px #ccc;
            background: rgba(0, 0, 0, 0.05);
            padding: 10px;
            margin: 10px 20px;
            display: inline-block;
        }

        figure>figcaption {
            text-align: center;
            display: block;
            /* For IE8 */
        }

        a>img {
            padding: 1px;
            margin: 1px;
            border: none;
            outline: 1px solid #0782C1;
        }

        /* Widget Styles */
        .code-featured {
            border: 5px solid red;
        }

        .math-featured {
            padding: 20px;
            box-shadow: 0 0 2px rgba(200, 0, 0, 1);
            background-color: rgba(255, 0, 0, 0.05);
            margin: 10px;
        }

        .image-clean {
            border: 0;
            background: none;
            padding: 0;
        }

        .image-clean>figcaption {
            font-size: .9em;
            text-align: right;
        }

        .image-grayscale {
            background-color: white;
            color: #666;
        }

        .image-grayscale img,
        img.image-grayscale {
            filter: grayscale(100%);
        }

        .embed-240p {
            max-width: 426px;
            max-height: 240px;
            margin: 0 auto;
        }

        .embed-360p {
            max-width: 640px;
            max-height: 360px;
            margin: 0 auto;
        }

        .embed-480p {
            max-width: 854px;
            max-height: 480px;
            margin: 0 auto;
        }

        .embed-720p {
            max-width: 1280px;
            max-height: 720px;
            margin: 0 auto;
        }

        .embed-1080p {
            max-width: 1920px;
            max-height: 1080px;
            margin: 0 auto;
        }
    </style>
</head>

<body class="pdfbody">
    <?php echo $html; ?>
</body>
</html>