jQuery(document).ready(function($) {
    var totalFiles = 0, processedFiles = 0, currentSourceType = "manual";
    var isProcessing = false;

    // Tabs
    $(".bm-tablink").on("click", function() {
        var tabId = $(this).data("tab");
        $(".bm-tablink").removeClass("active");
        $(this).addClass("active");
        $(".bm-tabcontent").hide();
        $("#" + tabId).show();
    });

    // File upload
    $("#bm-start-import-file").on("click", function() {
        if (isProcessing) {
            $("#bm-status-message").text("⏳ Import already in progress...");
            return;
        }
        var file = $("#bm-file-upload")[0].files[0];
        if (!file) { alert("Please select a file first."); return; }
        currentSourceType = "file";
        startImport("file", null, file);
    });

    // Manual URLs
    $("#bm-start-import-manual").on("click", function() {
        if (isProcessing) {
            $("#bm-status-message").text("⏳ Import already in progress...");
            return;
        }
        var urls = $("#bm-urls-textarea").val();
        if (!urls.trim()) { alert("Please enter at least one URL."); return; }
        currentSourceType = "manual";
        startImport("manual", urls, null);
    });

    function startImport(type, urlsData, fileObj) {
        isProcessing = true;

        var formData = new FormData();
        formData.append("action", "bulk_media_import_process");
        formData.append("step", 0);
        formData.append("nonce", bulk_media_ajax.nonce);
        formData.append("source_type", type);
        if (type === "file" && fileObj) formData.append("file", fileObj);
        else if (type === "manual" && urlsData) formData.append("urls", urlsData);

        $("#bm-progress-container").show();
        $("#bm-log").empty();
        $(".progress-bar-fill").css("width", "0%").removeClass("bm-completed");
        $("#bm-status-message").text("Starting import...");

        $.ajax({
            url: bulk_media_ajax.ajax_url,
            type: "POST",
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    totalFiles = response.data.total || 0;
                    processedFiles = 0;
                    if (totalFiles === 0) {
                        $("#bm-status-message").text("⚠️ No valid URLs found.");
                        $(".progress-bar-fill").css("width", "100%").addClass("bm-completed");
                        isProcessing = false;
                        return;
                    }
                    processNextBatch();
                } else {
                    $("#bm-status-message").text("❌ " + response.data);
                    $("#bm-log").empty();
                    $(".progress-bar-fill").css("width", "100%").addClass("bm-completed");
                    isProcessing = false;
                }
            },
            error: function() {
                $("#bm-status-message").text("❌ AJAX error – please try again.");
                $(".progress-bar-fill").css("width", "100%").addClass("bm-completed");
                isProcessing = false;
            }
        });
    }

    function processNextBatch() {
        $.ajax({
            url: bulk_media_ajax.ajax_url,
            type: "POST",
            data: {
                action: "bulk_media_import_process",
                step: 1,
                nonce: bulk_media_ajax.nonce,
                source_type: currentSourceType
            },
            success: function(response) {
                if (response.success) {
                    var step = parseInt(response.data.step, 10);

                    if (step === 1) {
                        processedFiles = response.data.processed || 0;
                        var percent = totalFiles > 0 ? (processedFiles / totalFiles) * 100 : 0;
                        $(".progress-bar-fill").css("width", percent + "%");
                        $("#bm-status-message").text("Processing: " + processedFiles + " of " + totalFiles);

                        // Only add to table if successful (log_id exists)
                        if (response.data.log_id) {
                            appendTableRow(response.data);
                        }

                        setTimeout(function() {
                            processNextBatch();
                        }, 300);
                    } 
                    else if (step === 2) {
                        $("#bm-status-message").text("✅ " + response.data.message);
                        $(".progress-bar-fill").css("width", "100%").addClass("bm-completed");
                        isProcessing = false;
                        refreshLogsTable();
                    } else {
                        $("#bm-status-message").text("❌ Unexpected step returned.");
                        isProcessing = false;
                    }
                } else {
                    $("#bm-status-message").text("❌ Processing error: " + response.data);
                    $(".progress-bar-fill").css("width", "100%").addClass("bm-completed");
                    isProcessing = false;
                }
            },
            error: function() {
                $("#bm-status-message").text("❌ AJAX error during processing.");
                $(".progress-bar-fill").css("width", "100%").addClass("bm-completed");
                isProcessing = false;
            }
        });
    }

    function refreshLogsTable() {
        $.post(bulk_media_ajax.ajax_url, {
            action: "bulk_media_get_logs_table",
            nonce: bulk_media_ajax.nonce
        }, function(response) {
            if (response.success) {
                $("#bm-logs-table-wrapper").html(response.data.html);
                $(".bm-star-rating").each(function() {
                    highlightStars($(this), $(this).data("rating"));
                });
            } else {
                console.warn("Failed to refresh logs table.");
            }
        });
    }

    function appendTableRow(data) {
        var thumbHtml = data.thumbnail_url 
            ? '<img src="' + data.thumbnail_url + '" style="width:48px;height:48px;object-fit:cover;border-radius:8px;" />'
            : '<span class="dashicons dashicons-format-image"></span>';

        var rowHtml = '<tr data-logid="' + data.log_id + '">';
        rowHtml += '<td class="bm-thumb-col">' + thumbHtml + '</td>';
        rowHtml += '<td>' + escapeHtml(data.post_title) + '</td>';
        rowHtml += '<td>' + escapeHtml(data.file_size) + '</td>';
        rowHtml += '<td>' + escapeHtml(data.file_type) + '</td>';
        rowHtml += '<td><a href="' + data.source_url + '" target="_blank">View</a></td>';
        rowHtml += '<td class="bm-rating-col">';
        rowHtml += '<div class="bm-star-rating" data-logid="' + data.log_id + '" data-rating="0">';
        for (var i = 1; i <= 5; i++) {
            rowHtml += '<span class="dashicons dashicons-star-empty" data-star="' + i + '"></span>';
        }
        rowHtml += '</div></td>';
        rowHtml += '<td>' + data.timestamp + '</td>';
        rowHtml += '<td><button class="button button-small delete-single-log" data-id="' + data.log_id + '">Delete</button></td>';
        rowHtml += '</tr>';

        $("#imported-logs-table tbody").append(rowHtml);
        var $container = $("#imported-logs-table tbody tr:last .bm-star-rating");
        highlightStars($container, 0);
    }

    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Star rating
    $(document).on("mouseenter", ".bm-star-rating .dashicons", function() {
        var index = $(this).data("star");
        var $container = $(this).closest(".bm-star-rating");
        highlightStars($container, index);
    }).on("mouseleave", ".bm-star-rating", function() {
        var $container = $(this);
        var currentRating = $container.data("rating");
        highlightStars($container, currentRating);
    }).on("click", ".bm-star-rating .dashicons", function() {
        var $star = $(this);
        var $container = $star.closest(".bm-star-rating");
        var rating = $star.data("star");
        var logId = $container.data("logid");
        $.post(bulk_media_ajax.ajax_url, {
            action: "update_imported_file_rating",
            nonce: bulk_media_ajax.nonce,
            log_id: logId,
            rating: rating
        }).done(function(res) {
            if (res.success) {
                $container.data("rating", rating);
                highlightStars($container, rating);
            } else {
                $("#bm-status-message").text("❌ Failed to save rating.");
            }
        }).fail(function() {
            $("#bm-status-message").text("❌ AJAX error while saving rating.");
        });
    });

    function highlightStars($container, rating) {
        $container.find(".dashicons").each(function(i) {
            var starVal = $(this).data("star");
            if (starVal <= rating) {
                $(this).removeClass("dashicons-star-empty").addClass("dashicons-star-filled");
            } else {
                $(this).removeClass("dashicons-star-filled").addClass("dashicons-star-empty");
            }
        });
    }

    // Initialize ratings on page load
    $(".bm-star-rating").each(function() {
        highlightStars($(this), $(this).data("rating"));
    });

    // Delete single log
    $(document).on("click", ".delete-single-log", function() {
        if (!confirm("Delete this import log? The media file will remain in your library.")) return;
        var $btn = $(this);
        var logId = $btn.data("id");
        $.post(bulk_media_ajax.ajax_url, {
            action: "delete_single_import_log",
            nonce: bulk_media_ajax.nonce,
            log_id: logId
        }).done(function(res) {
            if (res.success) {
                $btn.closest("tr").remove();
            } else {
                $("#bm-status-message").text("❌ Delete failed.");
            }
        });
    });
});