/*!
 * Wedge
 *
 * These are the core JavaScript functions used on most pages generated by Wedge.
 *
 * @package wedge
 * @copyright 2010-2012 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

@language index;

var
	oThought,
	weEditors = [],
	_formSubmitted = false,
	_modalDone = false,

	we_confirm = $txt['generic_confirm_request'],
	we_loading = $txt['ajax_in_progress'],
	we_cancel = $txt['form_cancel'],
	we_delete = $txt['delete'],
	we_submit = $txt['form_submit'],
	we_ok = $txt['ok'],

	// Basic browser detection. $.browser is being deprecated in jQuery,
	// but v1.5 still has it, and Wedge will keep supporting it.
	ua = navigator.userAgent.toLowerCase(),

	// If you need support for more browsers, just test for $.browser.version yourself...
	is_opera = !!$.browser.opera,
	is_ff = !!$.browser.mozilla,

	// The webkit ones. Oh my, that's a long list... Right now we're only supporting iOS and generic Android browsers.
	is_webkit = !!$.browser.webkit,
	is_chrome = ua.indexOf('chrome') != -1,
	is_iphone = is_webkit && (ua.indexOf('iphone') != -1 || ua.indexOf('ipod') != -1),
	is_tablet = is_webkit && ua.indexOf('ipad') != -1,
	is_android = is_webkit && ua.indexOf('android') != -1,
	is_safari = is_webkit && !is_chrome && !is_iphone && !is_android && !is_tablet,

	// This should allow us to catch more touch devices like smartphones and tablets...
	is_touch = 'ontouchstart' in document.documentElement,

	// IE gets version variables as well. Do you have to ask why..?
	is_ie = !!$.browser.msie && !is_opera,
	is_ie6 = is_ie && $.browser.version == 6,
	is_ie7 = is_ie && $.browser.version == 7,
	is_ie8 = is_ie && $.browser.version == 8,
	is_ie8down = is_ie && $.browser.version < 9,
	is_ie9up = is_ie && !is_ie8down;

// Replace the default jQuery easing type for animations.
$.easing.swing2 = $.easing.swing;
$.easing.swing = function (x, t, b, c, d)
{
	return b + c * Math.sqrt(1 - (t = t / d - 1) * t);
};

String.prototype.php_htmlspecialchars = function ()
{
	return this.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
};

String.prototype.php_unhtmlspecialchars = function ()
{
	return this.replace(/&quot;/g, '"').replace(/&gt;/g, '>').replace(/&lt;/g, '<').replace(/&amp;/g, '&');
};

String.prototype.wereplace = function (oReplacements)
{
	var sSearch, sResult = this;
	// .replace() uses $ as a meta-character in replacement strings, so we need to convert it to $$$$ first.
	for (sSearch in oReplacements)
		sResult = sResult.replace(new RegExp('%' + sSearch + '%', 'g'), (oReplacements[sSearch] + '').replace(/\$/g, '$$$$'));

	return sResult;
};

/*
	A stylable alert() alternative.
	@string string: HTML content to show.
	[optional] @object e: the current event object, if any. Must be specified if the alert is called before moving to another page (e.g. submit).
	[optional] @function callback: a function to call after the user clicked OK.
*/
function say(string, e, callback)
{
	return _modalDone || reqWin('', 350, string, 2, callback || (e && !e.target ? e : 0), e && e.target ? e : 0);
}

/*
	A stylable confirm() alternative.
	@string string: HTML content to show.
	[optional] @object e: the current event object, if any. Must be specified if the event handler uses ask() to cancel or proceed.
	[optional] @function callback: a function to call after the user made their choice. function (answer) { if (answer) { They agreed. } }
*/
function ask(string, e, callback)
{
	return _modalDone || reqWin('', 350, string, 1, callback || (e && !e.target ? e : 0), e && e.target ? e : 0);
}

/*
	Open a new popup window. The first two params are for generic pop-ups, while the rest is for confirm/alert boxes.

	@string from: specifies the URL to open. Use 'this' on a link to automatically use its href value.
	@mixed desired_width: use custom width. Omit or set to 0 for default (480px). Height is always auto.

	@string string: if provided, this is the contents. Otherwise, it will be retrieved through Ajax.
	@int modal_type: is this a modal pop-up? (i.e. requires attention) 0 = no, 1 = confirm, 2 = alert
	@function callback: if this is a modal pop-up (confirm, alert...), you can set a function to call with a boolean
						param (true = OK, false = Cancel). You can force cancelling the event by returning false from it.
	@object e: again, if this is a modal pop-up, we may need to re-call the original event.
*/
function reqWin(from, desired_width, string, modal_type, callback, e)
{
	var
		help_page = from && from.href ? from.href : from,
		title = $(from).text(),
		viewport_width = $(window).width(),
		viewport_height = window.innerHeight || $(window).height(), // innerHeight is an iOS hack (fixed in jQuery 1.8)
		previous_target = $('#helf').data('src'),
		close_window = function ()
		{
			$('#help_pop').fadeOut(function () { $(this).remove(); });
		},
		animate_popup = function ()
		{
			var $section = $('section', this);

			// Ensure that the popup never goes past the viewport boundaries.
			$section
				.width(Math.min(desired_width || 480, viewport_width - 20))
				.css({
					maxWidth: viewport_width - 20 - $(this).width() + $section.width(),
					maxHeight: viewport_height - 20 - $(this).height() + $section.height()
				});

			// In case the height was set to auto, some browsers (ahem)
			// will misbehave. Reset it to a hard number.
			$section.height($section.height());

			$(this)
				.hide()
				.css({
					visibility: 'visible',
					left: (viewport_width - $(this).width()) / 2,
					top: (viewport_height - $(this).height()) / 2 - 20
				})
				// !! Can also use specialEasing for diversity...
				.animate({
					opacity: 'show',
					top: '+=20'
				})
				.dragslide();
		};

	// Try and get the title for the current link.
	if (!title)
	{
		var nextSib = from.nextSibling;
		// Newlines are seen as stand-alone text nodes, so skip these...
		while (nextSib && nextSib.nodeType == 3 && $.trim($(nextSib).text()) === '')
			nextSib = nextSib.nextSibling;
		// Get the final text, remove any dfn (description) tags, and trim the rest.
		title = $.trim($(nextSib).clone().find('dfn').remove().end().text());
	}

	// Clicking the help icon twice should close the popup.
	if ($('#help_pop').remove().length && previous_target == help_page)
		return false;

	// We create the popup inside a dummy div to fix positioning in freakin' IE6.
	$('body').append(
		$('<div></div>')
		.attr('id', 'help_pop')
		.width(viewport_width)
		.height(viewport_height)
		.css({ top: is_ie6 || is_iphone ? $(window).scrollTop() : 0 })
		.fadeIn()
		.append(
			$('<div></div>')
			.attr('id', 'helf')
			.data('src', help_page)
		)
	);

	if (modal_type)
		$('#helf')
			.html('<section class="nodrag confirm">' + string + '</section><footer><input type="button" class="submit'
				+ (modal_type == 1 ? ' floatleft" /><input type="button" class="delete floatright" />' : '" />') + '</footer>')
			.each(animate_popup)
			.find('input')
			.val(we_cancel)
			.click(function () {
				close_window();
				if (callback && callback.call(e ? e.target : this, $(this).hasClass('submit')) === false)
					return;
				if (e && $(this).hasClass('submit'))
				{
					_modalDone = true;
					$(e.target).trigger(e.type);
					_modalDone = false;
				}
			})
			.filter('.submit')
			.val(we_ok);
	else
		$('#helf')
			.load(help_page, { t: title }, animate_popup)
			// Clicking anywhere on the page should close the popup.
			.parent() // #help_pop
			.click(function (e) {
				// If we clicked somewhere in the popup, don't close it, because we may want to select text.
				if (!$(e.target).closest('#helf').length)
					close_window();
			});

	// Return false so the click won't follow the link ;)
	return false;
}

// Only allow form submission ONCE.
function submitonce()
{
	_formSubmitted = true;

	// If there are any editors warn them submit is coming!
	$.each(weEditors, function () { this.doSubmit(); });
}

function submitThisOnce(oControl)
{
	$('textarea', oControl.form || oControl).attr('readOnly', true);
	return !_formSubmitted;
}

// Checks for variable in an array.
function in_array(variable, theArray)
{
	return $.inArray(variable, theArray) != -1;
}

// Invert all checkboxes at once by clicking a single checkbox.
function invertAll(oInvertCheckbox, oForm, sMask)
{
	$.each(oForm, function () {
		if (this.name && !this.disabled && (!sMask || this.name.indexOf(sMask) === 0|| this.id.indexOf(sMask) === 0))
			this.checked = oInvertCheckbox.checked;
	});
}

// Bind all delayed inline events to their respective DOM elements.
function bindEvents(items)
{
	// If $items isn't specified, do it for all elements with a delayed event.
	$(items || '*[data-eve]').each(function ()
	{
		var that = $(this);
		$.each(that.attr('data-eve').split(' '), function () {
			that.bind(eves[this][0], eves[this][1]);
		});
	});
}

// Shows the page numbers by clicking the dots.
function expandPages(spanNode, firstPage, lastPage, perPage)
{
	var i = firstPage, pageLimit = 50, baseURL = $(spanNode).data('href');

	// Prevent too many pages from being loaded at once.
	if ((lastPage - firstPage) / perPage > pageLimit)
	{
		var oldLastPage = lastPage;
		lastPage = firstPage + perPage * pageLimit;
	}

	// Calculate the new pages.
	for (; i < lastPage; i += perPage)
		$(spanNode).before('<a href="' + baseURL.replace(/%1\$d/, i).replace(/%%/g, '%') + '">' + (1 + i / perPage) + '</a> ');

	if (oldLastPage)
		$(spanNode).before($(spanNode).clone().click(function () { expandPages(this, lastPage, oldLastPage, perPage); }));

	$(spanNode).remove();
}

// Create the div for the indicator, and add the image, link to turn it off, and loading text.
function show_ajax()
{
	$('body').append(
		$('<div id="ajax_in_progress"></div>')
			.html('<a href="#" onclick="hide_ajax();" title="' + (we_cancel || '') + '"></a>' + we_loading)
			.css(is_ie6 ? { position: 'absolute', top: $(window).scrollTop() } : {})
	);
}

function hide_ajax()
{
	$('#ajax_in_progress').remove();
}

// Rating boxes in Media area.
function ajaxRating()
{
	show_ajax();
	$.post(
		$('#ratingF').attr('action') + ';xml',
		{ rating: $('#rating').val() },
		function (XMLDoc) {
			$('#ratingE').html($('item', XMLDoc).text());
			$('#rating').sb();
			hide_ajax();
		}
	);
}

// This function takes the script URL, and adds a question mark (or semicolon) so we can
// append a query string (url) to it. It also replaces the host name with the current one,
// which is sometimes required for security reasons.
function weUrl(url)
{
	return we_script.replace(/:\/\/[^\/]+/g, '://' + location.host) + (we_script.indexOf('?') == -1 ? '?' : (we_script.search(/[?&;]$/) == -1 ? ';' : '')) + url;
}

// Get the text in a code tag.
function weSelectText(oCurElement)
{
	// The place we're looking for is one div up, and next door - if it's auto detect.
	var oCodeArea = oCurElement.parentNode.nextSibling, oCurRange;

	if (!!oCodeArea)
	{
		// Start off with IE
		if ('createTextRange' in document.body)
		{
			oCurRange = document.body.createTextRange();
			oCurRange.moveToElementText(oCodeArea);
			oCurRange.select();
		}
		// Firefox et al.
		else if (window.getSelection)
		{
			var oCurSelection = window.getSelection();
			// Safari is special!
			if (oCurSelection.setBaseAndExtent)
			{
				var oLastChild = oCodeArea.lastChild;
				oCurSelection.setBaseAndExtent(oCodeArea, 0, oLastChild, (oLastChild.innerText || oLastChild.textContent).length);
			}
			else
			{
				oCurRange = document.createRange();
				oCurRange.selectNodeContents(oCodeArea);

				oCurSelection.removeAllRanges();
				oCurSelection.addRange(oCurRange);
			}
		}
	}

	return false;
}


(function ()
{
	var origMouse, currentPos, currentDrag = 0;

	// You may set an area as non-draggable by adding the nodrag class to it.
	// This way, you can drag the element, but still access UI elements within it.
	$.fn.dragslide = function () {
		// Updates the position during the dragging process
		$(document)
			.mousemove(function (e) {
				if (currentDrag)
				{
					$(currentDrag).css({
						left: currentPos.x + e.pageX - origMouse.x,
						top: currentPos.y + e.pageY - origMouse.y
					});
					return false;
				}
			})
			.mouseup(function () {
				if (currentDrag)
					return !!(currentDrag = 0);
			});

		return this
			.css('cursor', 'move')
			// Start the dragging process
			.mousedown(function (e) {
				if ($(e.target).closest('.nodrag').length)
					return true;

				$(this).css('zIndex', 999);

				origMouse = { x: e.pageX, y: e.pageY };
				currentPos = { x: parseInt(this.offsetLeft, 10), y: parseInt(this.offsetTop, 10) };
				currentDrag = this;

				return false;
			})
			.find('.nodrag')
			.css('cursor', 'default');
	};

})();


/**
 * Dropdown menu in JS with CSS fallback, Nao style.
 * May not show, but it took years to refine it.
 */

(function ()
{
	var menu_baseId = 0, menu_delay = [],

	// Entering a menu entry?
	menu_show_me = function ()
	{
		var
			hasul = $('ul', this)[0], style = hasul ? hasul.style : {}, is_visible = style.visibility == 'visible',
			id = this.id, parent = this.parentNode, is_top = parent.className == 'menu', d = document.dir, w = parent.clientWidth;

		if (hasul)
		{
			style.visibility = 'visible';
			style.opacity = 1;
			style[d && d == 'rtl' ? 'marginRight' : 'marginLeft'] = (is_top ? $('span', this).width() || 0 : w - 5) + 'px';
		}

		if (!is_top || !$('h4', this).first().addClass('hove').length)
			$(this).addClass('hove').parentsUntil('.menu>li').filter('li').addClass('hove');

		if (!is_visible)
			$('ul', this).first()
				.css(is_top ? { marginTop: is_ie6 || is_ie7 ? 12 : 39 } : { marginLeft: w })
				.animate(is_top ? { marginTop: is_ie6 || is_ie7 ? 6 : 33 } : { marginLeft: w - 5 });

		clearTimeout(menu_delay[id.substring(2)]);

		$(this).siblings('li').each(function () { menu_hide_children(this.id); });
	},

	// Leaving a menu entry?
	menu_hide_me = function (e)
	{
		// The deepest level should hide the hover class immediately.
		if (!$(this).children('ul').length)
			$(this).children().andSelf().removeClass('hove');

		// Are we leaving the menu entirely, and thus triggering the time
		// threshold, or are we just switching to another menu item?
		var id = this.id;
		$(e.relatedTarget).closest('.menu').length ?
			menu_hide_children(id) :
			menu_delay[id.substring(2)] = setTimeout(function () { menu_hide_children(id); }, 250);
	},

	// Hide all children menus.
	menu_hide_children = function (id)
	{
		$('#' + id).children().andSelf().removeClass('hove').find('ul').css({ visibility: 'hidden' }).css(is_ie8down ? '' : 'opacity', 0);
	};

	// Make sure to only call this on one element...
	$.fn.menu = function ()
	{
		var $elem = this.show();
		this.find('li').each(function () {
			$(this).attr('id', 'li' + menu_baseId++)
				.bind('mouseenter focus', menu_show_me)
				.bind('mouseleave blur', menu_hide_me)
				// Disable double clicks...
				.mousedown(false)
				// Clicking a link will immediately close the menu -- giving a feeling of responsiveness.
				.filter(':has(>a,>h4>a)')
				.click(function () {
					$('.hove').removeClass('hove');
					$elem.find('ul').css({ visibility: 'hidden' }).css(is_ie8down ? '' : 'opacity', 0);
				});
		});

		// Now that JS is ready to take action... Disable the pure CSS menu!
		$('.css.menu').removeClass('css');
		return this;
	};
})();


// *** weToggle class.
function weToggle(opt)
{
	var
		that = this,
		collapsed = false,
		toggle_me = function () {
			$(this).data('that').toggle();
			this.blur();
			return false;
		};

	// Change State - collapse or expand the section.
	this.cs = function (bCollapse, bInit)
	{
		// Handle custom function hook before collapse.
		if (!bInit && bCollapse && opt.onBeforeCollapse)
			opt.onBeforeCollapse.call(this);

		// Handle custom function hook before expand.
		else if (!bInit && !bCollapse && opt.onBeforeExpand)
			opt.onBeforeExpand.call(this);

		// Loop through all the images that need to be toggled.
		$.each(opt.aSwapImages || [], function () {
			$('#' + this.sId).toggleClass('fold', !bCollapse).attr('title', bCollapse && this.altCollapsed ? this.altCollapsed : this.altExpanded);
		});

		// Loop through all the links that need to be toggled.
		$.each(opt.aSwapLinks || [], function () {
			$('#' + this.sId).html(bCollapse && this.msgCollapsed ? this.msgCollapsed : this.msgExpanded);
		});

		// Now go through all the sections to be collapsed.
		$.each(opt.aSwapContainers, function () {
			$('#' + this)[bCollapse ? 'slideUp' : 'slideDown'](bInit ? 0 : 250);
		});

		// Update the new state.
		collapsed = +bCollapse;

		// Update the cookie, if desired.
		if (opt.sCookie)
			document.cookie = opt.sCookie + '=' + collapsed;

		// Set a theme option through javascript.
		if (!bInit && opt.sOptionName)
			$.get(weUrl('action=jsoption;var=' + opt.sOptionName + ';val=' + collapsed + ';' + we_sessvar + '=' + we_sessid + (opt.sExtra || '') + ';time=' + $.now()));
	};

	// Reverse the current state.
	this.toggle = function ()
	{
		this.cs(!collapsed);
	};

	// Note that this is only used in stats.js...
	this.opt = opt;

	// If the init state is set to be collapsed, collapse it.
	// If cookies are enabled and our toggler cookie is set to '1', override the initial state.
	// Note: the cookie retrieval code is below, you can turn it into a function by replacing opt.sCookie with a param.
	// It's not used anywhere else in Wedge, which is why we won't bother with a weCookie object.
	if (opt.isCollapsed || (opt.sCookie && document.cookie.search('\\b' + opt.sCookie + '\\s*=\\s*1\\b') != -1))
		this.cs(true, true);

	// Initialize the images to be clickable.
	$.each(opt.aSwapImages || [], function () {
		$('#' + this.sId).show().data('that', that).click(toggle_me).css({ visibility: 'visible' }).css('cursor', 'pointer').mousedown(false);
	});

	// Initialize links.
	$.each(opt.aSwapLinks || [], function () {
		$('#' + this.sId).show().data('that', that).click(toggle_me);
	});
}


// *** JumpTo class.
function JumpTo(control, id)
{
	$('#' + control)
		.html('<select><option data-hide>=> ' + $('#' + control).text() + '</option></select>')
		.css({ visibility: 'visible' })
		.find('select').sb().focus(function ()
		{
			var sList = '', $val;

			show_ajax();

			// Fill the select box with entries loaded through Ajax.
			$.get(
				weUrl('action=ajax;sa=jumpto;xml'),
				function (XMLDoc) {
					$('we item', XMLDoc).each(function ()
					{
						var
							that = $(this),
							// This removes entities from the name...
							name = that.text().replace(/&(amp;)?#(\d+);/g, function (sInput, sDummy, sNum) { return String.fromCharCode(+sNum); });

						// Just for the record, we don't NEED to close the optgroup at the end
						// of the list, even if it doesn't feel right. Saves us a few bytes...
						if (that.attr('type') == 'c') // Category?
							sList += '<optgroup label="' + name + '">';
						else
							// Show the board option, with special treatment for the current one.
							sList += '<option value="' + (that.attr('url') || that.attr('id')) + '"'
									+ (that.attr('id') == id ? ' disabled>=> ' + name + ' &lt;=' :
										'>' + new Array(+that.attr('level') + 1).join('&nbsp;&nbsp;&nbsp;&nbsp;') + name)
									+ '</option>';
					});

					// Add the remaining items after the currently selected item.
					$('#' + control).find('select').unbind('focus').append(sList).sb().change(function () {
						location = parseInt($val = $(this).val()) ? weUrl('board=' + $val + '.0') : $val;
					});

					hide_ajax();
				}
			);
		});
}


// *** Thought class.
function Thought(opt)
{
	var
		ajaxUrl = weUrl('action=ajax;sa=thought;xml;'),

		// Make that personal text editable (again)!
		cancel = function () {
			$('#thought_form').siblings().show().end().remove();
		},

		interact_thoughts = function ()
		{
			var thought = $(this), tid = thought.data('tid'), mid = thought.data('mid') || '';
			if (tid)
				thought.after('<div class="thought_actions">'
					+ (thought.data('self') !== '' ? '' : '<input type="button" class="submit"><input type="button" class="delete">')
					+ '<input type="button" class="new"></div>').next()
				.find('.submit').val(opt.sEdit).click(function () { oThought.edit(tid, mid) })						// Submit button
				.next().val(we_delete).click(function (e) { return ask(we_confirm, e) && oThought.remove(tid); })	// Delete button
				.next().val(opt.sReply).click(function () { oThought.edit(tid, mid, true) });						// Reply button
		};

	// Show the input after the user has clicked the text.
	this.edit = function (tid, mid, is_new, text, p)
	{
		cancel();

		var
			thought = $('#thought_update' + tid), was_personal = thought.find('span').first().html(),
			privacies = opt.aPrivacy, privacy = (thought.data('prv') + '').split(','),

			cur_text = is_new ? text || '' : (was_personal.toLowerCase() == opt.sNoText.toLowerCase() ? '' : (was_personal.indexOf('<') == -1 ?
				was_personal.php_unhtmlspecialchars() : $('text', $.ajax(ajaxUrl + 'in=' + tid, { context: this, async: false }).responseXML).text())),

			pr = '';

		for (p in privacies)
			pr += '<option value="' + privacies[p][0] + '"' + (in_array(privacies[p][0] + '', privacy) ? ' selected' : '') + '>&lt;div class="privacy_' + privacies[p][1] + '"&gt;&lt;/div&gt;' + privacies[p][2] + '</option>';

		// Hide current thought and edit/modify/delete links, and add tools to write new thought.
		thought
			.toggle(tid && is_new)
			.after('<form id="thought_form"><input type="text" maxlength="255" id="ntho"><select id="npriv">' + pr
				+ '</select><input type="hidden" id="noid"><input type="button" class="save"><input type="button" class="cancel"></form>')
			.siblings('.thought_actions').hide();
		$('#noid').val(is_new ? 0 : thought.data('oid'))
			.next().val(we_submit).click(function () { oThought.submit(tid, mid || tid); })	// Save button
			.next().val(we_cancel).click(function () { oThought.cancel(); });				// Cancel button
		$('#ntho').focus().val(cur_text);
		$('#npriv').sb();

		return false;
	};

	// Event handler for removal requests.
	this.remove = function (tid)
	{
		var toDelete = $('#thought_update' + tid);

		show_ajax();

		$.post(
			ajaxUrl + 'remove',
			{ oid: toDelete.data('oid') }
		);

		// We'll be assuming Wedge uses table tags to show thought lists.
		toDelete.closest('tr').remove();

		hide_ajax();
	};

	// Event handler for clicking submit.
	this.submit = function (tid, mid)
	{
		show_ajax();

		$.post(
			ajaxUrl,
			{
				parent: tid,
				master: mid,
				oid: $('#noid').val(),
				privacy: $('#npriv').val(),
				text: $('#ntho').val()
			},
			function (XMLDoc)
			{
				var
					$text = $('text', XMLDoc),
					$new_thought = $('#new_thought'),
					new_id = '#thought_update' + (tid ? $text.attr('id') : '');

				// Is this a thought reply?
				if (!$(new_id).length)
				{
					$new_thought.after($('<tr>').addClass($new_thought.attr('class')).html(
						$new_thought.html().wereplace({
							date: $('date', XMLDoc).text(),
							text: $text.text()
						})
					));
					$(new_id).each(interact_thoughts);
				}
				// If not, it's food for thought -- either new, or an edit.
				else
					$(new_id).find('span').html($text.text());
				cancel();
				hide_ajax();
			}
		);
	};

	this.cancel = cancel;

	$('#thought_update')
		.attr('title', opt.sLabelThought)
		.click(function () { oThought.edit(''); });
	$('.thought').each(interact_thoughts);
}

/* Optimize:
_formSubmitted = _f
_modalDone = _c
*/
