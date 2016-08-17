/*!
 * Functions required by the avatar resizing feature.
 *
 * Wedge (http://wedge.org)
 * Copyright © 2010 René-Gilles Deberdt, wedge.org
 * Portions are © 2011 Simple Machines.
 * License: http://wedge.org/license/
 */

function we_avatarResize()
{
	var tempAvatars = [], i = 0, maxWidth = we_avatarMaxSize[0], maxHeight = we_avatarMaxSize[1];
	$('img.avatar').each(function () {
		tempAvatars[i] = new Image;
		tempAvatars[i].avatar = this;

		$(tempAvatars[i++]).on('load', function ()
		{
			var ava = this.avatar;
			ava.width = this.width;
			ava.height = this.height;
			if (maxWidth != 0 && this.width > maxWidth)
			{
				ava.height = (maxWidth * this.height) / this.width;
				ava.width = maxWidth;
			}
			if (maxHeight != 0 && ava.height > maxHeight)
			{
				ava.width = (maxHeight * ava.width) / ava.height;
				ava.height = maxHeight;
			}
		}).attr('src', this.src);
	});
}
