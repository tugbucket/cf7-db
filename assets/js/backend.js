(function($) {
  $(document).ready(function() {
    var export_title = _i18n.str2,
      export_title_html = "<style>h1{text-align: center !important;}body{padding: 1rem;}</style><h2 align='center'>"+_i18n.str1+"</span></h2>",
      export_subtitle = _i18n.str3,
      export_filename = _i18n.str4,
      export_excelsheetname = "Pepro CF7 Database",
      export_footer = _i18n.str5,
      export_footer_html = "<h2 align='center'>"+export_footer+"</h2>",
      errorTxt = _i18n.errorTxt,
      cancelTtl = _i18n.cancelTtl,
      confirmTxt = _i18n.confirmTxt,
      successTtl = _i18n.successTtl,
      submitTxt = _i18n.submitTxt,
      okTxt = _i18n.okTxt,
      closeTxt = _i18n.closeTxt,
      cancelbTn = _i18n.cancelbTn,
      sendTxt = _i18n.sendTxt,
      titleTx = _i18n.titleTx,
      expireNowE = _i18n.expireNowE,
      txtYes = _i18n.txtYes,
      txtNop = _i18n.txtNop;
    jconfirm.defaults = {
      title: '',
      titleClass: '',
      type: 'blue', // red green orange blue purple dark
      typeAnimated: true,
      draggable: true,
      dragWindowGap: 15,
      dragWindowBorder: true,
      animateFromElement: true,
      smoothContent: true,
      content: '',
      buttons: {},
      defaultButtons: {
        ok: {
          keys: ['enter'],
          text: okTxt,
          action: function() {}
        },
        close: {
          keys: ['enter'],
          text: closeTxt,
          action: function() {}
        },
        cancel: {
          keys: ['esc'],
          text: cancelbTn,
          action: function() {}
        },
      },
      contentLoaded: function(data, status, xhr) {},
      icon: '',
      lazyOpen: false,
      bgOpacity: null,
      theme: 'modern',
      /*light dark supervan material bootstrap modern*/
      animation: 'scale',
      closeAnimation: 'scale',
      animationSpeed: 400,
      animationBounce: 1,
      rtl: $("body").is(".rtl") ? true : false,
      container: 'body',
      containerFluid: false,
      backgroundDismiss: false,
      backgroundDismissAnimation: 'shake',
      autoClose: false,
      closeIcon: null,
      closeIconClass: false,
      watchInterval: 100,
      columnClass: 'm',
      boxWidth: '500px',
      scrollToPreviousElement: true,
      scrollToPreviousElementAnimate: true,
      useBootstrap: false,
      offsetTop: 40,
      offsetBottom: 40,
      bootstrapClasses: {
        container: 'container',
        containerFluid: 'container-fluid',
        row: 'row',
      },
      onContentReady: function() {},
      onOpenBefore: function() {},
      onOpen: function() {},
      onClose: function() {},
      onDestroy: function() {},
      onAction: function() {},
      escapeKey: true,
    };

    if ($('#exported_data').length){
      var table = $('#exported_data').DataTable({
      aaSorting: [
        [1, 'desc']
      ],
      "language": {
        "decimal": "",
        "emptyTable": _i18n.tbl1,
        "info": _i18n.tbl2,
        "infoEmpty": _i18n.tbl3,
        "infoFiltered": _i18n.tbl4,
        "infoPostFix": "",
        "thousands": ",",
        "lengthMenu": _i18n.tbl5,
        "loadingRecords": _i18n.tbl6,
        "processing": _i18n.tbl7,
        "search": _i18n.tbl8,
        "zeroRecords": _i18n.tbl9,
        "paginate": {
          "first": _i18n.tbl10,
          "last": _i18n.tbl11,
          "next": _i18n.tbl12,
          "previous": _i18n.tbl13
        },
        "aria": {
          "sortAscending": _i18n.tbl14,
          "sortDescending": _i18n.tbl15
        }
      },
      select: true,
      paging: false,
      fixedHeader: true,
      responsive: false,
      searchHighlight: true,
      dom: 'Bfrtip',
      buttons: [
        {
          extend: 'excel',
          text: _i18n.tbl19,
          title: export_title,
          footer: true,
          header: true,
          sheetName: export_excelsheetname,
          messageTop: _i18n.str1,
          messageBottom: export_footer,
          filename: export_filename,
          exportOptions: {
            columns: "thead th:not(.noExport)"
          },
        },
        {
          extend: 'csv',
          text: _i18n.tbl18,
          bom: true,
          filename: export_filename,
          exportOptions: {
            columns: "thead th:not(.noExport)"
          },
        },
        {
          extend: 'copy',
          footer: true,
          header: true,
          messageTop: export_title,
          title: _i18n.str1,
          messageBottom: export_footer,
          text: _i18n.tbl16,
          exportOptions: {
            columns: "thead th:not(.noExport)"
          },
        },
        {
          extend: 'print',
          text: _i18n.tbl17,
          title: export_title,
          footer: true,
          header: true,
          messageTop: export_title_html,
          messageBottom: export_footer_html,
          autoPrint: false,
          exportOptions: {
            columns: "thead th:not(.noExport)"
          },
        },
        {
          extend: 'colvis',
          text: _i18n.tbl177,}

      ]
    });
      table.on('draw', function() {
      var body = $(table.table().body());
      body.unhighlight();
      body.highlight(table.search());
    });
    }
    $(document).on("click tap", "#emptyDbNow", function(e) {
      e.preventDefault();
      var me = $(this);
      var jc = $.confirm({
          title: _i18n.clearDBConfTitle,
          content: _i18n.clearDBConfirmation,
          boxWidth: '600px',
          icon: 'fas fa-trash-alt',
          closeIcon: true,
          type: "red",
          animation: 'scale',
          buttons: {
              no: {
                text: txtNop,
                btnClass: 'btn-red',
                keys: ['n', 'esc'],
                action: function() {}
              },
              yes: {
                text: txtYes,
                btnClass: 'btn-red',
                keys: ['y', 'enter'],
                action: function() {
                  me.next(".spinner").css("visibility", "visible");
                  $(".jconfirm-closeIcon").hide();
                  jc.showLoading(true);
                  jc.setBoxWidth("400px");
                  $.ajax({
                    type: 'POST',
                    dataType: "json",
                    url: _i18n.ajax,
                    data: {
                      action: _i18n.td,
                      nonce: _i18n.nonce,
                      wparam: "clear_db",
                    },
                    success: function(result) {
                      jc.close();
                      if (result.success === true) {
                        $.confirm({
                          title: successTtl,
                          content: result.data.msg,
                          icon: 'fas fa-check-circle',
                          type: 'green',
                          boxWidth: '400px',
                          buttons: {
                            close: {
                              text: closeTxt,
                              keys: ['enter', 'esc'],
                              action: function() {}
                            }
                          }
                        });
                      } else {
                        $.confirm({
                          title: errorTxt,
                          content: result.data.msg,
                          icon: 'fa fa-exclamation-triangle',
                          type: 'red',
                          boxWidth: '400px',
                          buttons: {
                            close: {
                              text: closeTxt,
                              keys: ['enter', 'esc'],
                              action: function() {}
                            }
                          }
                        });
                      }
                    }
                  });
                  return false;
                }
              },
          }
      });
    });
    $(document).on("click tap", "#emptySelectedCf7DB", function(e) {
      e.preventDefault();
      var me = $(this);
      var jc = $.confirm({
          title: _i18n.clearDBConfTitle,
          content: _i18n.clearDBConfirmatio2,
          boxWidth: '600px',
          icon: 'fas fa-trash-alt',
          closeIcon: true,
          type: "red",
          animation: 'scale',
          buttons: {
              no: {
                text: txtNop,
                btnClass: 'btn-red',
                keys: ['n', 'esc'],
                action: function() {}
              },
              yes: {
                text: txtYes,
                btnClass: 'btn-red',
                keys: ['y', 'enter'],
                action: function() {
                  me.next(".spinner").css("visibility", "visible");
                  $(".jconfirm-closeIcon").hide();
                  jc.showLoading(true);
                  jc.setBoxWidth("400px");
                  $.ajax({
                    type: 'POST',
                    dataType: "json",
                    url: _i18n.ajax,
                    data: {
                      action: _i18n.td,
                      nonce: _i18n.nonce,
                      wparam: "clear_db_cf7",
                      lparam: me.data("rel"),
                    },
                    success: function(result) {
                      jc.close();
                      if (result.success === true) {
                        $("#exported_data tbody tr").remove();
                        $.confirm({
                          title: successTtl,
                          content: result.data.msg,
                          icon: 'fas fa-check-circle',
                          type: 'green',
                          boxWidth: '400px',
                          buttons: {
                            close: {
                              text: closeTxt,
                              keys: ['enter', 'esc'],
                              action: function() {}
                            }
                          }
                        });
                      } else {
                        $.confirm({
                          title: errorTxt,
                          content: result.data.msg,
                          icon: 'fa fa-exclamation-triangle',
                          type: 'red',
                          boxWidth: '400px',
                          buttons: {
                            close: {
                              text: closeTxt,
                              keys: ['enter', 'esc'],
                              action: function() {}
                            }
                          }
                        });
                      }
                    }
                  });
                  return false;
                }
              },
          }
      });
    });
    $(document).on("click tap", ".dt-button.hrefbtn", function(e) {
      e.preventDefault();
      let me = $(this);
      window.location.href = me.attr("href");
    });
    $(document).on('change',    "#itemsperpagedisplay", function() {
      $("form#mainform").submit();
    });
    $(document).on("click tap", "a.delete_item", function(e) {
      e.preventDefault();
      let me = $(this);
      var id = me.data("lid");
      $(`tr.highlight`).removeClass("highlight");
      $(`tr.item_${id}`).addClass("highlight");
      var jc = $.confirm({
        title: _i18n.deleteConfirmTitle,
        content: _i18n.deleteConfirmation.replace("%s", `<u><strong>${id}</strong></u>`),
        boxWidth: '600px',
        icon: 'fas fa-trash-alt',
        closeIcon: true,
        animation: 'scale',
        buttons: {
          no: {
            text: txtNop,
            btnClass: 'btn-red',
            keys: ['n', 'esc'],
            action: function() {
              $(`tr.highlight`).removeClass("highlight");
            }
          },
          yes: {
            text: txtYes,
            btnClass: 'btn-red',
            keys: ['y', 'enter'],
            action: function() {
              me.next(".spinner").css("visibility", "visible");
              $(".jconfirm-closeIcon").hide();
              jc.showLoading(true);
              jc.setBoxWidth("400px");
              $.ajax({
                type: 'POST',
                dataType: "json",
                url: _i18n.ajax,
                data: {
                  action: _i18n.td,
                  nonce: _i18n.nonce,
                  wparam: "delete_item",
                  lparam: id,
                },
                success: function(result) {
                  jc.close();
                  me.next(".spinner").css("visibility", "hidden");
                  if (result.success === true) {
                    $(`#exported_data .item_${id}`).remove();
                    $.confirm({
                      title: successTtl,
                      content: result.data.msg,
                      icon: 'fas fa-check-circle',
                      type: 'green',
                      boxWidth: '400px',
                      buttons: {
                        close: {
                          text: closeTxt,
                          keys: ['enter', 'esc'],
                          action: function() {}
                        }
                      }
                    });
                  } else {
                    $.confirm({
                      title: errorTxt,
                      content: result.data.msg,
                      icon: 'fa fa-exclamation-triangle',
                      type: 'red',
                      boxWidth: '400px',
                      buttons: {
                        close: {
                          text: closeTxt,
                          keys: ['enter', 'esc'],
                          action: function() {}
                        }
                      }
                    });
                  }
                },
                error: function(result) {
                  jc.close();
                  me.next(".spinner").css("visibility", "hidden");
                  $.confirm({
                    title: errorTxt,
                    content: "UNKNOWN ERROR OCCURED.",
                    icon: 'fa fa-exclamation-triangle',
                    type: 'red',
                    boxWidth: '400px',
                    buttons: {
                      close: {
                        text: closeTxt,
                        keys: ['enter', 'esc'],
                        action: function() {}
                      }
                    }
                  });
                },
                complete: function(result) {
                  $(`tr.highlight`).removeClass("highlight");
                }
              });
              return false;
            }
          },
        },
      });
    });

    function scroll(e, of = 0) {
      $('html, body').animate({
        scrollTop: e.offset().top - of
      }, 500);
    }

  });
})(jQuery);
