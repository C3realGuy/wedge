/*!
 * All code related to showing rotating elements on a page, e.g. news fader.
 *
 * Wedge (http://wedge.org)
 * Copyright © 2010 René-Gilles Deberdt, wedge.org
 * License: http://wedge.org/license/
 */

function weFader(opt)
{
	var
		aFaderItems = [],
		fadeIndex = 0,
		fadeDelay = opt.delay || 5000,		// How long until the next item transition?
		fadeSpeed = opt.speed || 650,		// How long should the transition effect last?
		sTemplate = opt.template || '%1$s',	// Put some nice HTML around the items?
		sControlId = '#' + opt.control,

		fadeIn = function ()
		{
			fadeIndex++;
			if (fadeIndex >= aFaderItems.length)
				fadeIndex = 0;

			$(sControlId + ' li').html(sTemplate.replace('%1$s', aFaderItems[fadeIndex])).fadeTo(fadeSpeed, 0.99, function () {
				// Remove alpha filter for IE, to restore ClearType.
				this.style.filter = '';
				fadeOut();
			});
		},
		fadeOut = function ()
		{
			setTimeout(function () { $(sControlId + ' li').fadeTo(fadeSpeed, 0, fadeIn); }, fadeDelay);
		};

	// Load the items from the DOM.
	$(sControlId + ' li').each(function () {
		aFaderItems.push($(this).html());
	});

	if (aFaderItems.length)
	{
		// Well, we are replacing the contents of a list, it *really* should be a list item we add in to it...
		$(sControlId).html('<li>' + sTemplate.replace('%1$s', aFaderItems[0]) + '</li>').show();
		fadeOut();
	}
}
