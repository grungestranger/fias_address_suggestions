$(function(){

	var region;

	$('#address > input').on('input', function(){
		var input = $(this);
		$.ajax({
			url: '/suggestions',
			data: {
				region: region,
				str: input.val(),
			},
			success: function(data) {
				if (data.success) {
					input.siblings('.suggestions').html('');
					if (data.final) {
						$('#hints').html('Полный адрес');
					} else {
						$.each(data.items, function(k, v) {
							$('#hints').append('<div class="hint" style="cursor: pointer;">' + v.address + '</div>');
						});
					}
				}
			}
		});
	});

	$('#region > input').on('input', function(){
		var input = $(this);
		$.ajax({
			url: '/regions',
			data: {
				str: input.val(),
			},
			success: function(data) {
				if (data.success) {
					$('.suggestions').html('');
					if (data.final) {
						input.val(data.name);
						regionComplete(data.code);
					} else {
						$.each(data.items, function(k, v) {
							input.siblings('.suggestions').append('<div data-code="' + v.code + '">' + v.name + '</div>');
						});
					}
				}
			}
		});
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

	}

});
