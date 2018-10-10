
/* вспомогательные функции */

/* текущее время */
function nowTime() {
	var now = new Date();
	var day = (now.getDate() < 10 ? '0' : '') + now.getDate();
	var month = (parseInt(now.getMonth() + 1) < 10 ? '0' : '') + parseInt(now.getMonth() + 1);
	var year = now.getFullYear();
	var hours = (now.getHours() < 10 ? '0' : '') + now.getHours();
	var minutes = (now.getMinutes() < 10 ? '0' : '') + now.getMinutes();
	var seconds = (now.getSeconds() < 10 ? '0' : '') + now.getSeconds();
	return day + '.' + month + '.' + year + ' ' + hours + ':' + minutes + ':' + seconds + ' ';
}

/* перевод байт */
function сonvertBytes(size) {
	var filesizename = [" Bytes", " KB", " MB", " GB", " TB", " PB", " EB", " ZB", " YB"];
	return size ? (size / Math.pow(1024, (i = Math.floor(Math.log(size) / Math.log(1024))))).toFixed(2) + filesizename[i] : '0.00';
}

function showResult(text) {
	$("#topics_result").html(text);
}

var lock = 0;

function block_actions() {
	if (lock == 0) {
		$("#topics_control button").prop("disabled", true);
		$("#subsections").selectmenu("disable");
		$("#loading, #process").show();
		lock = 1;
	} else {
		$("#topics_control button").prop("disabled", false);
		if ($("#subsections").val() < 1 || !$("input[name=filter_status]").eq(1).prop("checked")) {
			$(".tor_add").prop("disabled", true);
		} else {
			$(".tor_stop, .tor_remove, .tor_label, .tor_start").prop("disabled", true);
		}
		$("#subsections").selectmenu("enable");
		$("#loading, #process").hide();
		lock = 0;
	}
}
// выполнить функцию с задержкой
function makeDelay(ms) {
	var timer = 0;
	return function (callback, scope) {
		clearTimeout(timer);
		timer = setTimeout(function () {
			callback.apply(scope);
		}, ms);
	}
}

// сортировка в select
function doSortSelect(select_id) {
	var sortedVals = $.makeArray($('#' + select_id + ' option')).sort(function (a, b) {
		if ($(a).val() == 0) return -1;
		return $(a).text().toUpperCase() > $(b).text().toUpperCase() ? 1 : $(a).text().toUpperCase() < $(b).text().toUpperCase() ? -1 : 0;
	});
	$('#' + select_id).empty().html(sortedVals);
}

function doSortSelectByValue(select_id) {
	var sortedVals = $.makeArray($('#' + select_id + ' option')).sort(function (a, b) {
		if ($(a).val() == 0) return -1;
		return $(a).val().toUpperCase() > $(b).val().toUpperCase() ? 1 : $(a).val().toUpperCase() < $(b).val().toUpperCase() ? -1 : 0;
	});
	$('#' + select_id).empty().html(sortedVals);
}

// получение отчётов
function getReport($event, ui) {
	var forum_id = ui.item.value;
	if ($.isEmptyObject(forum_id)) {
		return false;
	}
	$.ajax({
		type: "POST",
		url: "php/actions/get_reports.php",
		data: { forum_id: forum_id },
		beforeSend: function () {
			$("#reports_list").selectmenu("disable");
			$("#report_content").html("<i class=\"fa fa-spinner fa-pulse\"></i>");
		},
		success: function (response) {
			var response = $.parseJSON(response);
			$("#log").append(response.log);
			$("#report_content").html(response.report);
			//инициализация "аккордиона" сообщений
			$("#report_content .report_message").each(function () {
				$(this).accordion({
					collapsible: true,
					heightStyle: "content"
				});
			});
			// выделение тела сообщения двойным кликом
			$("#report_content .ui-accordion-content").dblclick(function () {
				var e = this;
				if (window.getSelection) {
					var s = window.getSelection();
					if (s.setBaseAndExtent) {
						s.setBaseAndExtent(e, 0, e, e.childNodes.length);
					} else {
						var r = document.createRange();
						r.selectNodeContents(e);
						s.removeAllRanges();
						s.addRange(r);
					}
				} else if (document.getSelection) {
					var s = document.getSelection();
					var r = document.createRange();
					r.selectNodeContents(e);
					s.removeAllRanges();
					s.addRange(r);
				} else if (document.selection) {
					var r = document.body.createTextRange();
					r.moveToElementText(e);
					r.select();
				}
			});
		},
		complete: function () {
			$("#reports_list").selectmenu("enable");
		},
	});
}
