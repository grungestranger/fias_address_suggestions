$(function(){

	var region, timerId, request;

	$('#form input').on('input', function(){
		if (timerId !== undefined) {
			clearTimeout(timerId);
		}
		var input = $(this);
		timerId = setTimeout(function(){
			requestAbort();
			getSuggestions(input);
		}, 200);
	});

	function requestAbort() {
		if (request !== undefined) {
			request.abort();
		}
	}

	function getSuggestions(input) {
		if (input.parents('#region').length) {
			var type = 'regions';
		} else {
			var type = 'suggestions';
		}
		var data = {str: input.val()};
		if (type == 'suggestions') {
			data['region'] = region;
		}
		request = $.ajax({
			url: 'http://prettyaddress.ru/' + type,
			data: data,
			success: function(data) {
				if (data.success) {
					$('.suggestions').html('').hide();
					if (data.final) {
						if (type == 'suggestions') {
							$('#form').addClass('completed');
						} else {
							regionComplete(data.code);
						}
					} else {
						$('#form').removeClass('completed');
						if (type == 'regions') {
							$('#address > input').prop('readonly', true).val('');
						}
						input.siblings('.suggestions').show();
						$.each(data.items, function(k, v) {
							if (type == 'suggestions') {
								var str = '<div>' + v.address + '</div>';
							} else {
								var str = '<div data-code="' + v.code + '">' + v.name + '</div>';
							}
							input.siblings('.suggestions').append(str);
						});
					}
				}
			}
		});
	}

	$(document).on('click', '.suggestions > div', function(){
		requestAbort();
		$(this).parent().siblings('input').val($(this).text());
		if ($(this).parents('#region').length) {
			regionComplete($(this).data('code'));
		} else {
			getSuggestions($('#address > input'));
		}
		$('.suggestions').html('').hide();
	});

	function regionComplete(code) {
		region = code;
		$('#form').removeClass('completed');
		$('#address > input').prop('readonly', false).val('').focus();
	}

	$('#allRegions').click(function(){
		requestAbort();
		request = $.ajax({
			url: 'http://prettyaddress.ru/regions',
			success: function(data) {
				if (data.success) {
					$('#region .suggestions').show();
					$.each(data.items, function(k, v) {
						$('#region .suggestions').append('<div data-code="' + v.code + '">' + v.name + '</div>');
					});
				}
			}
		});
	});

	var closeFlag = 0;
	$('.suggestions').click(function(){
		closeFlag = 1;
	});

	$('body').click(function(){
		if (closeFlag) {
			closeFlag = 0;
		} else {
			$('.suggestions').html('').hide();
		}
	});

	$('.suggestions').niceScroll({
		cursorcolor: '#c6d0d9',
		cursorwidth: '3px',
		cursorborder: 'none',
		autohidemode: false
	});

});
