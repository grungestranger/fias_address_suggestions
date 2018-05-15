$(function(){

	var region, timerId;

	$('#form input').on('input', function(){
		if (timerId !== null) {
			clearTimeout(timerId);
		}
		var input = $(this);
		timerId = setTimeout(function(){
			if (input.parents('#region').length) {
				var type = 'regions';
			} else {
				var type = 'suggestions';
			}
			var data = {str: input.val()};
			if (type == 'suggestions') {
				data['region'] = region;
			}
			$.ajax({
				url: 'http://prettyaddress.ru/' + type,
				data: data,
				success: function(data) {
					if (data.success) {
						$('.suggestions').html('');
						if (data.final) {
							if (type == 'suggestions') {
								input.siblings('.suggestions').html('Полный адрес');
							} else {
								regionComplete(data.code);
							}
						} else {
							if (type == 'regions') {
								$('#address > input').prop('disabled', true).val('');
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
		}, 200);
	});

	$(document).on('click', '.suggestions > div', function(){
		if ($(this).parents('#region').length) {
			regionComplete($(this).data('code'));
		} else {
			$('#address > input').trigger('input');
		}
		$(this).parent().siblings('input').val($(this).text());
		$(this).parent().hide();
		$(this).parent().html('');
	});

	function regionComplete(str) {
		region = str;
		$('#address > input').prop('disabled', false).focus();
	}

	$('#allRegions').click(function(){
		$.ajax({
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

});
