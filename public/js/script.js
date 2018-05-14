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
				url: '/' + type,
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
								$('#address > input').prop('disabled', true);
							}
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
		}, 100);
	});

	$(document).on('click', '.suggestions > div', function(){
		$(this).parent().siblings('input').val($(this).text());
		$(this).parent().html('');
		if ($(this).parents('#region').length) {
			regionComplete($(this).data('code'));
		} else {
			$('#address > input').trigger('input');
		}
	});

	function regionComplete(str) {
		region = str;
		$('#address > input').prop('disabled', false);
	}

});
