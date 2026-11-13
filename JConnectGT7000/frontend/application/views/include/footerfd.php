   </main>
   </div>

   <script src="<?php echo iaBase(); ?>assets/js/custom.js"></script>
   <script src="<?php echo iaBase() . 'assets/ckeditor/ckeditor.js'; ?>"></script>
   <script src="<?php echo iaBase() . 'assets/ckeditor/adapters/jquery.js'; ?>"></script>


   <script src="<?php echo iaBase(); ?>assets/plugins/popper/overlayscrollbars.browser.es6.min.js"></script>
   <script src="<?php echo iaBase(); ?>assets/plugins/popper/popper.min.js"></script>

   <script src="<?php echo iaBase(); ?>assets/plugins/bootstrap/js/bootstrap.min.js"></script>
   <script src="<?php echo iaBase(); ?>assets/plugins/select2/select2.full.min.js"></script>
   <script src="<?php echo iaBase(); ?>assets/adminlte/js/adminlte.js"></script>
   <script src="<?php echo iaBase(); ?>assets/js/sidebar-state.js"></script>

   <!--
 <script src="<?php echo iaBase(); ?>assets/plugins/DataTables/datatables.min.js"></script>
 -->

   <script src="<?php echo iaBase(); ?>assets/plugins/DataTables/jquery.dataTables.min.js"></script>
   <script src="<?php echo iaBase(); ?>assets/plugins/DataTables/dataTables.bootstrap5.min.js"></script>
   <script src="<?php echo iaBase(); ?>assets/plugins/DataTables/dataTables.buttons.min.js"></script>
   <script src="<?php echo iaBase(); ?>assets/plugins/DataTables/buttons.bootstrap5.min.js"></script>
   <script src="<?php echo iaBase(); ?>assets/plugins/DataTables/buttons.html5.min.js"></script>
   <script src="<?php echo iaBase(); ?>assets/plugins/DataTables/buttons.print.min.js"></script>
   <script src="<?php echo iaBase(); ?>assets/plugins/DataTables/jszip.min.js"></script>
   <script src="<?php echo iaBase(); ?>assets/plugins/DataTables/pdfmake.min.js"></script>
   <script src="<?php echo iaBase(); ?>assets/plugins/DataTables/vfs_fonts.js"></script>
   <!--
Day.js core -->
   <script src="<?php echo iaBase(); ?>assets/plugins/dayjs/dayjs.min.js"></script>

   <!-- Plugins -->
   <script src="<?php echo iaBase(); ?>assets/plugins/dayjs/utc.js"></script>
   <script src="<?php echo iaBase(); ?>assets/plugins/dayjs/timezone.js"></script>



   <script>
     $(document).ready(function() {

       setTimeout(function() {
         $('.page-loader-wrapper').fadeOut();
       }, 50);

       /**
        * Check for session alert messages and display them
        * $type may be success, danger, warning, info
        */
       <?php
        $this->load->helper('notification_helper');
        $alert_msg = get_alert_message();
        if ($alert_msg) {
        ?>
         $msg = '<?php echo addslashes($alert_msg['msg']); ?>';
         $type = '<?php echo $alert_msg['type']; ?>';
         showNotification($msg, $type);
       <?php
        }
        ?>
     });
   </script>








   <script>
     const SELECTOR_SIDEBAR_WRAPPER = '.sidebar-wrapper';
     const Default = {
       scrollbarTheme: 'os-theme-light',
       scrollbarAutoHide: 'leave',
       scrollbarClickScroll: true,
     };
     document.addEventListener('DOMContentLoaded', function() {
       const sidebarWrapper = document.querySelector(SELECTOR_SIDEBAR_WRAPPER);
       if (sidebarWrapper && typeof OverlayScrollbarsGlobal?.OverlayScrollbars !== 'undefined') {
         OverlayScrollbarsGlobal.OverlayScrollbars(sidebarWrapper, {
           scrollbars: {
             theme: Default.scrollbarTheme,
             autoHide: Default.scrollbarAutoHide,
             clickScroll: Default.scrollbarClickScroll,
           },
         });
       }
     });


     if (typeof Event === 'function') {
       window.dispatchEvent(new Event('resize')); // All browsers other than IE
     } else {
       // IE support
       var resizeEvent = window.document.createEvent('UIEvents');
       resizeEvent.initUIEvent('resize', true, false, window, 0);
       window.dispatchEvent(resizeEvent);
     }
   </script>


   </body>

   </html>

   <!-- The Modal -->
   <div id="mka_image_preview_modal" role="dialog" class="modal fade">
     <!-- The Close Button -->
     <div class="modal-dialog">
       <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
       <!-- Modal Content (The Image) -->
       <img class="modal-content" id="img01">
     </div>
   </div>

   <div class="modal fade" id="cnfrm_delete" tabindex="-1" role="dialog">
     <div class="modal-dialog modal-sm" role="document">
       <div class="modal-content">
         <div class="modal-header">
           <h4 class="modal-title" id="smallModalLabel"><?php echo lang("Confirmation"); ?></h4>
         </div>
         <div class="modal-body">
           <?php echo lang("Doyoureallywanttodelete"); ?>
         </div>
         <div class="modal-footer">
           <a type="button" href="" class="btn btn-primary waves-effect yes-btn"><?php echo lang("Yes"); ?></a>
           <button type="button" class="btn btn-danger waves-effect" data-dismiss="modal"><?php echo lang("No"); ?></button>
         </div>
       </div>
     </div>
   </div>