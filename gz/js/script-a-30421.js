/*!
 * These are the core JavaScript functions used on most pages generated by Wedge.
 *
 * Wedge (http://wedge.org)
 * Copyright © 2010 René-Gilles Deberdt, wedge.org
 * Portions are © 2011 Simple Machines.
 * License: http://wedge.org/license/
 */var oThought,weEditors=[],_f,_c=!1,notload,we_confirm='Are you sure you want to do this?',we_loading='Loading...',we_cancel='Cancel',we_delete='Delete',we_submit='Submit',we_ok='OK',ua=function(a){return navigator.userAgent.toLowerCase().indexOf(a)!=-1},is_webkit=ua('webkit'),is_chrome=is_webkit&&ua('chrome'),is_ios=is_webkit&&ua('(ip'),is_android=is_webkit&&ua('android'),is_safari=is_webkit&&!is_chrome&&!is_android&&!is_ios,is_touch='ontouchstart'in window||typeof window.Touch==='object',is_opera=ua('opera'),is_ie=ua('msie')||ua('trident'),is_ie6=is_ie&&ua('msie 6'),is_ie7=is_ie&&ua('msie 7'),is_ie8=is_ie&&ua('msie 8'),is_ie8down=is_ie6||is_ie7||is_ie8,is_firefox=!is_webkit&&!is_ie&&ua('mozilla');$.easing.swing2=$.easing.swing;$.easing.swing=function(a,b,c,d,e){return c+d*Math.sqrt(1-(b=b/e-1)*b)};String.prototype.php_htmlspecialchars=function(){return this.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;')};String.prototype.php_unhtmlspecialchars=function(){return this.replace(/&quot;/g,'"').replace(/&gt;/g,'>').replace(/&lt;/g,'<').replace(/&amp;/g,'&')};String.prototype.wereplace=function(a){var b,c=this;for(b in a)c=c.replace(new RegExp('%'+b+'%','g'),(a[b]+'').split('$').join('$$'));return c};$.fn.realWidth=function(){return parseFloat(getComputedStyle(this[0]).width)};$.fn.realHeight=function(){return parseFloat(getComputedStyle(this[0]).height)};function say(a,b,c){return reqWin('',350,a,0,c||(b&&!b.target?b:0),b&&b.target?b:0)}function ask(a,b,c){return _c||reqWin('',350,a,1,c||(b&&!b.target?b:0),b&&b.target?b:0)}function reqWin(c,d,e,h,i,l){var g=c&&c.href?c.href:c,k=$(c).hasClass('fadein')?$(c).parent().attr('title'):(c&&c.href?$(c).text():0),n=Math.min(window.innerWidth||$(window).width(),$(window).width()),r=Math.min(window.innerHeight||$(window).height(),$(window).height()),q=$('#helf').data('src'),o=function(a){$('#popup,#helf').removeClass('show');setTimeout(function(){if(/^[.#]/.test(e+''))$(e).append($('#helf').contents());$('#popup').remove();if((i&&i.call(l?l.target:this,a)===!1)||!l||!a)return;_c=!0;if(l.target.href)location=l.target.href;else $(l.target).trigger(l.type);_c=!1},300)},p=function(){var a=$(this),b=a.find('section').first();if(!b.length)b=$('<section>').append(a.contents()).appendTo(a);if(!a.find('header').first().length)a.prepend('<header>'+k+'</header>');if(!a.find('footer').first().length)a.append('<footer><input type="button" class="submit'+(h?' floatleft"><input type="button" class="delete floatright':'')+'"></footer>');b.addClass('nodrag').width(Math.min(d||480,n-20)).css({maxWidth:n-20-a.width()+b.width(),maxHeight:r-20-a.height()+b.height()});a.css({left:(n-a.width())/2,top:(r-a.height())/2}).ds();a.find('.submit,.delete').click(function(){o($(this).hasClass('submit'))}).each(function(){if($(this).val()=='')$(this).val($(this).hasClass('delete')?we_cancel:we_ok)});$('#popup,#helf').addClass('show')};if(!k){var j=c.nextSibling;while(j&&j.nodeType==3&&$.trim($(j).text())==='')j=j.nextSibling;k=$.trim($(j).clone().find('dfn').remove().end().text())}if($('#popup').remove().length&&q&&q==g)return!1;$('body').append($('<div>').attr('id','popup').width(n).height(r).css('top',is_ie6||is_ios?$(window).scrollTop():0).append($('<div>').attr('id','helf').data('src',g)));if(g)$('#helf').load(g,p);else $('#helf').html(/^[.#]/.test(e+'')?$(e).contents():e).each(p);$('#helf').parent().click(function(a){if(!h&&!$(a.target).closest('#helf').length)o(!1)});return!1}function submitonce(){_f=!0;$.each(weEditors,function(){this.doSubmit()})}function submitThisOnce(a){$('textarea',a.form||a).attr('readOnly',!0);return!_f}function in_array(a,b){return $.inArray(a,b)!=-1}function invertAll(a,b,c){$.each(b,function(){if(this.name&&!this.disabled&&(!c||this.name.indexOf(c)===0||this.id.indexOf(c)===0))this.checked=a.checked})}function breakLinks(b){$(b||'.bbc_link').each(function(){var a=$(this).text();if(a==this.href&&a.length>50)$(this).html(a.slice(0,25)+'<span class="cut"><span>'+a.slice(25,-25)+'</span></span><wbr>'+a.slice(-25))})}function expandPages(a,b,c,d){var e=b,h=50,i=$(a).data('href');if((c-b)/d>h){var l=c;c=b+d*h}for(;e<c;e+=d)$(a).before('<a href="'+i.replace(/%1\$d/,e).replace(/%%/g,'%')+'">'+(1+e/d)+'</a> ');if(l)$(a).off().click(function(){expandPages(this,c,l,d)});else $(a).remove()}function show_ajax(b,c){window.ajax=setTimeout(function(){var a=$(b).offset();$('<div id="ajax">').html('<a title="'+(we_cancel||'')+'"></a>'+we_loading).click(hide_ajax).addClass('anim').appendTo('body');$('#ajax').offset({left:(a&&a.left||0)+(c&&c[0]||0)+(b?$(b).outerWidth():Math.min(window.innerWidth||$(window).width(),$(window).width()))/2-$('#ajax').outerWidth()/2,top:(a&&a.top||0)+(c&&c[1]||0)+(b?$(b).outerHeight():Math.min(window.innerHeight||$(window).height(),$(window).height()))/2-$('#ajax').outerHeight()/2+(b?0:$(window).scrollTop())}).removeClass('anim')},200)}function hide_ajax(){clearTimeout(window.ajax);$('#ajax').addClass('anim');setTimeout(function(){$('#ajax').remove()},200)}function ajaxRating(){show_ajax();$.post($('#ratingF').attr('action'),{rating:$('#rating').val()},function(a){$('#ratingF').parent().html(a);$('#rating').sb();hide_ajax()})}function weUrl(a){return a.indexOf(we_script)>=0?a:we_script.replace(/:\/\/[^\/]+/g,'://'+location.host)+(we_script.indexOf('?')==-1?'?':(we_script.search(/[?&;]$/)==-1?';':''))+a}function weSelectText(a){var b=a.parentNode.nextSibling,c;if(!!b){if('createTextRange'in document.body){c=document.body.createTextRange();c.moveToElementText(b);c.select()}else if(window.getSelection){var d=window.getSelection();if(d.setBaseAndExtent){var e=b.lastChild;d.setBaseAndExtent(b,0,e,(e.innerText||e.textContent).length)}else{c=document.createRange();c.selectNodeContents(b);d.removeAllRanges();d.addRange(c)}}}return!1}$.fn.ds=function(){var b,c,d=0;$(document).mousemove(function(a){if(d){$(d).css({left:c.x+a.pageX-b.x,top:c.y+a.pageY-b.y});return!1}}).mouseup(function(){if(d)return!!(d=0)});this.css('cursor','move').mousedown(function(a){if($(a.target).closest('.nodrag').length)return!0;$(this).css('zIndex',999);b={x:a.pageX,y:a.pageY};c={x:parseInt(this.offsetLeft,10),y:parseInt(this.offsetTop,10)};d=this;return!1}).find('.nodrag').css('cursor','default')};$.fn.mime=function(a,b){return this.mimenu(a,b)};$.fn.title=function(a){return this.mimenu(a,'',!0)};$.fn.mimenu=function(k,n,r){return this.each(function(){if(this.className.indexOf('processed')>=0||$(this).addClass('processed').parent().hasClass('mime'))return;var a=$(this),b=a.data('id')||(a.closest('.msg').attr('id')||'').slice(3),c=$('<div class="mimenu">').hide(),d,e=$('<ul>'),h,i,l,g;if(k&&k[b]){e.addClass('actions');$.each(k[b],function(){d=n[this.slice(0,2)];$('<a>').html(d[0].wereplace({1:b,2:this.slice(3)})).attr('title',d[1]).attr('class',d[3]).attr('href',d[2]?(d[2][0]=='?'?a.attr('href')||'':'')+d[2].wereplace({1:b,2:this.slice(3)}):a.attr('href')||'').click(new Function('e',d[4]?d[4].wereplace({1:b,2:this.slice(3)}):'')).wrap('<li>').parent().appendTo(e)})}else e.html(k);c.html(e).click(function(){c.stop(!0).animate(h,200,function(){c.hide()})});a.wrap('<span class="mime"></span>').after(c).parent().hover(function(){c.show();l=c.width();g=c.height();c.toggleClass('right',a.offset().left>$(window).width()/2).toggleClass('top',r||!1);h={opacity:0,width:l/2,height:g/2,paddingTop:0,paddingBottom:0};i={opacity:1,width:l,height:g,paddingTop:c.css('paddingTop'),paddingBottom:c.css('paddingBottom')};c.css(h).stop(!0).show().animate(i,300,function(){c.attr('style','').css('overflow','visible')})},function(){c.stop(!0).animate(h,200,function(){c.attr('style','').hide()})})})};$(function(){breakLinks();$('select').sb();$('.menu>li>span').each(function(){$(this).wrap($('<a/>').attr('href',$(this).next().find('a').attr('href')))});$('[data-eve]').each(function(){var a=$(this);$.each(a.attr('data-eve').split(' '),function(){a.on(eves[this][0],eves[this][1])})});if(is_touch){$('.umme,.subsection>a,.menu>li:not(.nodrop)>h4>a').click(!1);var b=$('meta[name=viewport]'),c=b.attr('content');$('input,textarea').focus(function(){b.attr('content',c+',maximum-scale=1,user-scalable=0')}).blur(function(){b.attr('content',c)})}else $('.menu a').click(function(a){if(a.which!=2)$(this).parentsUntil('.menu>li').addClass('done')});var d,e,h,i=0,l=!1,g,k=$('#edge'),n=k.children().not('#sidebar').first(),r=function(){if(l||$('#sideshow').is(':hidden'))return!0;e=n.width();d=parseInt(k.css('left'))||0;l=!0;n.width(e);$('#wedge').addClass('sliding');$('#sidebar').css('display',n.css('display'));$('#sidebar>div').css('margin-top',$(window).scrollTop()>$('#sidebar').offset().top?Math.min($('#sidebar').height()-$('#sidebar>div').height(),$(window).scrollTop()-$('#sidebar').offset().top):0);$(document).on('mousedown.sw',function(a){if(!$(a.target).closest('#sidebar').length&&a.target.tagName!='HTML')o()});h=$('#sidebar').offset().left>n.offset().left;k.css({left:d-(h?0:$('#sidebar').width())});if(!$('#sideshow').closest('#edge').length)k.css({transform:'translate3d('+(h?'-':'')+$('#sidebar').width()+'px,0,0)'});g=k.css('transform')!='none';if(!g)k.stop(!0).animate({left:d-(h?$('#sidebar').width():0)},500);$(window).on('resize.sw',function(){if(l&&$('#sideshow').is(':hidden'))q()})},q=function(){l=!1;$('#sidebar>div').attr('style','');$('#sidebar').attr('style','');k.attr('style','');n.width('');$(window).off('.sw');$(document).off('.sw');$('#wedge').removeClass('sliding')},o=function(){if(g){setTimeout(q,500);k.css({transform:'none'})}else k.stop(!0).animate({left:d-(h?0:$('#sidebar').width())},500,q)};$(document).on(is_firefox?'mouseup':'mousedown',function(a){if(a.which==2&&!$(a.target).closest('a').addBack().filter('a').length&&!$('#sideshow').is(':hidden')&&!$.hasData(a.target)){l?o():r();a.preventDefault()}});$('<div/>').attr('id','sideshow').attr('title','Click here, or middle-click anywhere on the page, to toggle the sidebar.').click(function(){l?o():r()}).prependTo('#top_section>div')});$(window).on('load',function(){$('#upshrink').attr('title','Shrink or expand this.');new weToggle({isCollapsed:!!window.we_colhead,aSwapContainers:['banner'],aSwapImages:['upshrink'],sOption:'collapse_header'});var d,e=$('<div class="mimenu right">').appendTo('#search_form'),h=$('#search_form .search'),i=function(a){var b=e.parent().offset().top,c=b+e.parent().height();e.css({top:a?b:c,right:$('body').width()-Math.max(e.width()-15,e.parent().offset().left+e.parent().width())}).toggle(!a).animate({opacity:'toggle',top:a?c:b})};h.focus(function(){if(h.hasClass('open'))return;h.addClass('open');if(!d)e.load(weUrl('action=search'+(window.we_topic?';topic='+we_topic:'')+(window.we_board?';board='+we_board:'')),function(){d=!0;e.find('select').sb();if(h.hasClass('open'))i(!0)});else i(!0);$(document).off('.sf').on('click.sf keyup.sf',function(a){if((a.keyCode&&(a.keyCode!=9||a.altKey||a.ctrlKey))||$(a.target).closest('#search_form').length)return;h.removeClass('open');$(document).off('.sf');i(!1)})})});$(function(){var q=!1,o=!1,p=!1,j=!1,t=document.title,f=$('.notifs.notif'),m=$('.notifs.npm'),s=$('<div/>').addClass('mimenu').appendTo(f),u=$('<div/>').addClass('mimenu').appendTo(m),z=function(){p=!p;s.toggleClass('open');f.toggleClass('hover');$(document).off('click.no');if(!p)return;$(document).on('click.no',function(a){if($(a.target).closest(f).length)return;p=!p;s.toggleClass('open');f.toggleClass('hover');$(document).off('click.no')})},x=function(){j=!j;u.toggleClass('open');m.toggleClass('hover');$(document).off('click.no');if(!j)return;$(document).on('click.no',function(a){if($(a.target).closest(m).length)return;j=!j;u.toggleClass('open');m.toggleClass('hover');$(document).off('click.no')})},B=function(e,h,i,l,g,k,n,r){show_ajax(i,[0,30]);l.load(e,function(){hide_ajax();l.prev().addClass('notevoid').removeClass('note');$(this).find('.n_container').css('max-height',($(window).height()-$(this).find('.n_container').offset().top)*.9).closest('ul').css('max-width',$(window).width()*.95);$(this).find('.n_item').each(function(){var c=$(this),d=$(this).data('id');$(this).hover(function(){$(this).toggleClass('windowbg3').find('.n_read').toggle()}).click(function(){if(!c.next('.n_prev').stop(!0,!0).slideToggle(600).length&&c.data('prev')!='no'){show_ajax(this);$.post(weUrl(g.wereplace({id:d})),function(a){hide_ajax();$('<div/>').addClass('n_prev').html(a).insertAfter(c).hide().slideToggle(600);if(n)n.call(c,a,d)})}else if(n)n.call(c,'',d)});if(r)$(this).find('.n_read').hover(function(){$(this).toggleClass('windowbg')}).click(function(a){var b=c.hasClass('n_new');c.removeClass('n_new').next('.n_prev').addBack().hide(300,function(){$(this).remove()});if(b){we_notifs--;s.prev().text(we_notifs);document.title=(we_notifs>0?'('+we_notifs+') ':'')+t;$.post(weUrl('action=notification;sa=markread;in='+d))}a.stopImmediatePropagation();return!1})});if(h&&k)k.call(this)})};notload=function(c,d){B(c,d,f,s,'action=notification;sa=preview;in=%id%',z,function(a,b){if(this.hasClass('n_new')){this.removeClass('n_new');we_notifs--;s.prev().text(we_notifs);document.title=(we_notifs>0?'('+we_notifs+') ':'')+t;$.post(weUrl('action=notification;sa=markread;in='+b))}},!0)};f.click(function(a){if(a.target!=this)return!0;if(j)x();if(!q){notload(weUrl('action=notification'),!0);q=!0}else z()});m.click(function(a){if(a.target!=this)return!0;if(p)z();if(!o){B(weUrl('action=pm;sa=ajax'),!0,m,u,'action=pm;sa=ajax;preview=%id%',x,!1);o=!0}else x()});var v=function(){$.post(weUrl('action=notification;sa=unread'),function(a){a=a.split(';');if(a[0]!==''&&a[0]!=window.we_notifs){if(we_notifs>a[0])s.prev().addClass('notevoid').removeClass('note');we_notifs=a[0];q=!1;s.prev().text(we_notifs);document.title=(we_notifs>0?'('+we_notifs+') ':'')+t}if(a[1]!=='-1'&&a[1]!=window.we_pms){if(we_pms>a[1])u.prev().addClass('notevoid').removeClass('note');we_pms=a[1];o=!1;u.prev().text(we_pms)}});setTimeout(v,document.hidden||document.webkitHidden||document.mozHidden||document.msHidden||is_ie8down?600000:60000)};setTimeout(v,document.hidden||document.webkitHidden||document.mozHidden||document.msHidden||is_ie8down?600000:60000)});function weToggle(c){var d=this,e=!1,h=function(){$(this).data('that').toggle();this.blur();return!1};this.cs=function(a,b){if(!b&&a&&c.onCollapse)c.onCollapse.call(this);else if(!b&&!a&&c.onExpand)c.onExpand.call(this);$.each(c.aSwapImages||[],function(){$('#'+this).toggleClass('fold',!a).attr('title',c.title||'Shrink or expand this.')});$.each(c.aSwapContainers||[],function(){$('#'+this)[a?'slideUp':'slideDown'](b?0:250)});e=+a;if(!b&&c.sOption)$.post(weUrl('action=ajax;sa=opt;'+we_sessvar+'='+we_sessid+(c.sExtra||'')),{v:c.sOption,val:e})};this.toggle=function(){this.cs(!e)};this.opt=c;if(c.isCollapsed)this.cs(!0,!0);$.each(c.aSwapImages||[],function(){$('#'+this).show().data('that',d).click(h).css({visibility:'visible'}).css('cursor','pointer').mousedown(!1)});$.each(c.aSwapLinks||[],function(){$('#'+this).show().data('that',d).click(h)})}function JumpTo(d){$('#'+d).html('<select><option data-hide>=> Select destination</option></select>').css({visibility:'visible'}).find('select').sb().focus(function(){var b='',c;show_ajax();$.post(weUrl('action=ajax;sa=jumpto'+(window.we_board?';board='+we_board:'')),function(a){$.each(a,function(){if(this.id)b+='<option value="'+this.id+'"'+(this.id=='skip'?' disabled>=> '+this.name+' &lt;=':'>'+new Array(+this.level+1).join('&nbsp;&nbsp;&nbsp;&nbsp;')+this.name)+'</option>';else b+='<optgroup label="'+this.name+'">'});$('#'+d).find('select').off('focus').html(b).sb().change(function(){location=parseInt(c=$(this).val())?weUrl('board='+c+'.0'):c});hide_ajax()})})}function PrivacySelector(a,b,c,d){var e,h,i='<option value="'+b+'"'+(a==b?' selected':'')+'>&lt;div class="privacy_public"&gt;&lt;/div&gt;Public</option>';i+='<option value="'+c+'"'+(a==c?' selected':'')+'>&lt;div class="privacy_members"&gt;&lt;/div&gt;Members</option>';i+='<option value="'+d+'"'+(a==d?' selected':'')+'>&lt;div class="privacy_author"&gt;&lt;/div&gt;Just me</option>';if(!$.isEmptyObject(we_lists)){i+='<optgroup label="Contacts">';for(h in we_lists){e=we_lists[h].split('|');i+='<option value="'+e[0]+'"'+(a==e[0]?' selected':'')+'>&lt;div class="privacy_list_'+e[1]+'"&gt;&lt;/div&gt;'+e[2]+'</option>'}i+='</optgroup>'}if(!$.isEmptyObject(we_groups)){i+='<optgroup label="Membergroup">';for(h in we_groups){e=we_groups[h].split('|');i+='<option value="-'+e[0]+'"'+(a==-e[0]?' selected':'')+'>&lt;div class="privacy_group"&gt;&lt;/div&gt;'+(e[1]>=0?'&lt;em&gt;'+e[2]+'&lt;/em&gt; &lt;small&gt;'+e[1]+'&lt;/small&gt;':e[2])+'</option>'}i+='</optgroup>'}return i}function Thought(g,k,n){var r,q=function(){$('#thought_form').siblings().show().end().remove()};this.edit=function(a,b,c){q();var d=$('#thought'+a),e=d.find('span').first().html(),h=c?'':(e.indexOf('<')==-1?e.php_unhtmlspecialchars():$.ajax(weUrl('action=ajax;sa=thought')+';in='+a,{async:!1}).responseText),i,l;r=c?0:d.data('oid')||0;d.toggle(c&&c!==1).after('<form id="thought_form"><input type="text" maxlength="255" id="ntho"><select id="npriv">'+PrivacySelector((d.data('prv')+'').split(','),g,k,n)+'</select><input type="submit" class="save"><input type="button" class="cancel"></form>');$('#npriv').next().val(we_submit).click(function(){return oThought.submit(a,b||a)}).next().val(we_cancel).click(q);$('#ntho').focus().val(h);$('#npriv').sb();return!1};this.personal=function(a){$.post(weUrl('action=ajax;sa=thought')+';in='+a+';personal');return!1};this.like=function(b){var c=$('#thought'+b).closest('.thoughts');show_ajax();$.post(weUrl('action=ajax;sa=thought')+';like;'+we_sessvar+'='+we_sessid,{cx:c.data('cx'),oid:b},function(a){c.html(a);hide_ajax()});return!1};this.remove=function(b){var c=$('#thought'+b).closest('.thoughts');show_ajax();$.post(weUrl('action=ajax;sa=thought'),{cx:c.data('cx'),oid:$('#thought'+b).data('oid')},function(a){c.html(a);hide_ajax()});return!1};this.submit=function(b,c){var d=$('#thought'+b).closest('.thoughts');show_ajax();$.post(weUrl('action=ajax;sa=thought'),{cx:d.data('cx'),oid:r,parent:b,master:c,privacy:$('#npriv').val(),text:$('#ntho').val()},function(a){d.html(a);hide_ajax();q()});return!1};$('#thought0').click(function(){oThought.edit(0,0,1)}).find('span').html('(Click here to send a thought)')}
/*!
 * Selectbox replacement plugin for Wedge.
 *
 * Developed and customized/optimized for Wedge by Nao.
 * Contains portions by RevSystems (SelectBox)
 * and Maarten Baijs (ScrollBar).
 *
 * Wedge (http://wedge.org)
 * Copyright © 2010 René-Gilles Deberdt, wedge.org
 * License: http://wedge.org/license/
 */(function(){var E=0,K=function(g){var k=is_opera?'keypress.sb keydown.sb':'keydown.sb',n=g.hasClass('fixed'),r,q,o,p,j,t,f,m,s,u,z=function(){m=$('<div class="sbox '+(g.attr('class')||'')+'" id="sb'+(g.attr('id')||++E)+'" role="listbox">').attr('aria-haspopup',!0);t=$('<div class="display" id="sbd'+(++E)+'">').append(B(g.data('default')||g.find('option:selected')).replace(/<small>.*?<\/small>/,'')).append('<div class="btn">&#9660;</div>');f=$('<div class="items" id="sbdd'+E+'" role="menu" onselectstart="return!1;">').attr('aria-hidden',!0);m.append(t,f).on('close',v).attr('aria-owns',f.attr('id')).find('.details').remove();if(!g.children().length)f.append(x().addClass('selected'));else g.children().each(function(){var a=$(this),b;if(a.is('optgroup')){f.append(b=$('<div class="optgroup"><div class="label">'+a.attr('label')+'</div>'));a.find('option').each(function(){f.append(x($(this)).addClass('sub'))});if(a.is(':disabled'))b.nextAll().addBack().addClass('disabled').attr('aria-disabled',!0)}else f.append(x(a))});s=f.children().not('.optgroup');F(u=s.filter('.selected,:has(input:checked)').first());f.children().first().addClass('first');f.children().last().addClass('last');g.addClass('sb').before(m);if(n&&s.not('.disabled').length)m.width(f.width()+$('.btn',t).outerWidth()+2);f.hide();if(g.attr('data-eve'))$.each(g.attr('data-eve').split(' '),function(){t.on(eves[this][0],eves[this][1])});if(!g.is(':disabled')){g.on('focus.sb',function(){H();w()});t.blur(I).focus(w).mousedown(!1).hover(function(){$(this).toggleClass('hover')}).click(J);s.not('.disabled').hover(function(){if(m.hasClass('open'))F($(this))},function(){$(this).removeClass('selected');j=u}).mousedown(L);f.children('.optgroup').mousedown(!1);s.filter('.disabled').mousedown(!1);if(!is_ie8down)$(window).on('resize.sb',function(){clearTimeout(r);r=setTimeout(function(){if(m.hasClass('open'))C(1)},50)})}else{m.addClass('disabled').attr('aria-disabled',!0);t.click(!1)}},x=function(a){a=a||$('<option>');var b=a.attr('data-hide')!=='';return $('<div id="sbo'+(++E)+'" role="option" class="pitem">').data('orig',a).data('value',a.attr('value')||'').attr('aria-disabled',!!a.is(':disabled')).toggleClass('disabled',!b||a.is(':disabled,.hr')).toggleClass('selected',a.is(':selected')&&(!g.attr('multiple')||a.hasClass('single'))).toggle(b).append($('<div class="item">').attr('style',a.attr('style')||'').addClass(a.attr('class')).append(B(a,!g.attr('multiple')||a.hasClass('single')?'':'<input type="checkbox" onclick="this.checked = !this.checked;"'+(a.is(':selected')?' checked':'')+'>')))},B=function(a,b){return'<div class="text">'+(b||'')+((a.text?a.text().split('|').join('</div><div class="details">'):a+'')||'&nbsp;')+'</div>'},v=function(a){if(m.hasClass('open')){p='';t.blur();m.removeClass('open');f.removeClass('has_bar');f.animate(is_opera?{opacity:'toggle'}:{opacity:'toggle',height:'toggle'},a==1?0:100);f.attr('aria-hidden',!0);f.find('.scrollbar').remove();f.find('.overview').contents().unwrap().unwrap()}$(document).off('.sb')},G=function(a){m.removeClass('focused');v();if(j.data('value')!==g.val())A(u,!0);if(a===1)w()},H=function(){$('.sbox.focused').not(m).find('.display').blur()},D=function(){if(!j.length)return;if(p)p.st(j.is(':hidden')?0:j.position().top,j.height());else f.scrollTop(f.scrollTop()+j.offset().top-f.offset().top-f.height()/2+j.outerHeight(!0)/2)},J=function(a){m.hasClass('open')?G(1):C(0,1);a&&a.stopPropagation()},C=function(a,b){H();b?g.triggerHandler('focus'):w();f.stop(!0,!0).show().css({visibility:'hidden'}).width('').height('').removeClass('above').find('.viewport').height('');f.find('small').css('float','none');var c=f.realWidth();f.find('.details').toggle();f.width(Math.min($('body').width(),Math.max((f.realWidth()+c)/2,t.outerWidth()-f.outerWidth(!0)+f.realWidth())));f.find('.details').toggle();f.find('small').css('float','');var d=f.outerHeight(),e=$(window).scrollTop()+Math.min($(window).height(),$('body').height())-t.offset().top-t.outerHeight(),h=t.offset().top-$(window).scrollTop(),i=Math.max(Math.min(d,50),Math.min(Math.max(500,d/5),d,Math.max(e,h-50)-50)),l=(i<=e)||((i>=h)&&(e>=h-50));if(i<d){f.height(i-d+f.height());p=new M(f);D()}j.addClass('selected');f.attr('aria-hidden',!1).toggleClass('above',!l).css({visibility:'visible',marginTop:l?0:-i-t.outerHeight(),marginLeft:Math.min(0,$('body').width()-f.outerWidth()-m.offset().left)}).hide();if(q)g.triggerHandler('click');f.animate(!l||is_opera?{opacity:'toggle'}:{opacity:'toggle',height:'toggle'},a?0:200);m.addClass('open')},A=function(a,b,c){var d=a.find('.text'),e=t.find('.text'),h=e.width(),i;if(!b&&!m.hasClass('open'))C();F(a,c);e.width('').html((d.html()||'&nbsp;').replace(/<small>.*?<\/small>/,'')).attr('title',d.text().php_unhtmlspecialchars());i=e.width();if(!n)e.stop(!0,!0).width(h).delay(100).animate({width:i})},F=function(a,b){if(!a.length||!in_array(a[0],s.get()))a=u;if(g.attr('multiple')&&!a.has('>.single').length)j=s.filter('.selected,:has(input:checked)').first();else{j=a.addClass('selected');s.not(j).removeClass('selected');if(b)s.not(j).find('input').prop('checked',!1)}m.attr('aria-activedescendant',j.attr('id'))},y=function(){o=g.val()!==j.data('value')&&!j.hasClass('disabled');if(g.attr('multiple'))s.each(function(){if($(this).data('orig')[0].selected!=$(this).find('input').prop('checked'))$(this).data('orig')[0].selected=$(this).find('input').prop('checked')||$(this).hasClass('selected')});else{g.find('option')[0].selected=!1;j.data('orig')[0].selected=!0}u=j},L=function(a){if(a.which==1&&(!g.attr('multiple')||$(this).has('>.single').length)){A($(this),!1,!0);y();G();w()}else if(a.which==1){s.filter('.selected').removeClass('selected');$(this).find('input').prop('checked',!$(this).find('input').prop('checked'));F($(this));y()}if(o){g.triggerHandler('change');o=!1}return!1},N=function(a){var b=s.not('.disabled'),c=b.index(j)+1,d=b.length,e=c;while(!0){for(;e<d;e++)if(b.eq(e).text().toLowerCase().match('^'+a.toLowerCase()))return A(b.eq(e))||!0;if(!c)return!1;d=c;c=e=0}},O=function(a){if(a.altKey||a.ctrlKey)return;var b=s.not('.disabled');q=!0;if(a.keyCode==9){if(m.hasClass('open')){y();v()}I()}else if(a.which==32){m.hasClass('open')?G():(C(),D());w();a.preventDefault()}else if((a.keyCode==8||a.keyCode==13)&&m.hasClass('open')){y();v();w();a.preventDefault()}else if(a.keyCode==35){A(b.last(),!0);D();y();a.preventDefault()}else if(a.keyCode==36){A(b.first(),!0);D();y();a.preventDefault()}else if(a.keyCode==38||a.keyCode==40){A(b.eq((b.index(j)+(a.keyCode==38?-1:1))%b.length),!0);D();y();a.preventDefault()}else if(a.which<91&&N(String.fromCharCode(a.which)))a.preventDefault();if(o){g.triggerHandler('change');o=!1}q=!1},w=function(){$('.sbox.open').not(m).trigger('close');m.addClass('focused');$(document).off('.sb').on(k,O).on('mousedown.sb',G)},I=function(){m.removeClass('focused');$(document).off(k)};this.re=function(){var a=m.hasClass('open'),b=m.hasClass('focused');v(1);m.remove();g.removeClass('sb').off('.sb');$(window).off('.sb');z();if(a)C(1);else if(b)w()};this.open=J;z()},M=function(c){var d=this,e=0,h,i=0,l=0,g,k,n,r,q,o,p,j,t=function(a){d.st(e+a.pageY-h);return!1},f=function(a,b){if(is_touch&&is_chrome)a.css({transform:'translate3d(0,'+parseInt(b)+'px,0)'});else a.css({top:parseInt(b)})};d.st=function(a,b){if(b)a=(a-k/2+b/2)/p;i=Math.min(k-g,Math.max(0,a))*p;f(o,l=i/p);f(r,-i)};if(c.find('.viewport').length)return;c.addClass('has_bar').width(Math.min(c.width(),$('body').width()));c.contents().wrapAll('<div class="viewport"><div class="overview">');c.append('<div class="scrollbar"><div>');k=c.height();c.find('.viewport').height(k);q=c.find('.scrollbar').height(k);r=c.find('.overview');n=r.height();o=q.find('div');p=n/k;g=Math.min(k,k/p);h=o.offset().top;o.height(g);q.mousedown(t);o.mousedown(function(a){h=a.pageY;e=l;$(document).on('mousemove.sc',t).on('mouseup.sc',function(){$(document).off('.sc');return!1});return!1});c.on('DOMMouseScroll mousewheel',function(a){i=Math.min(n-k,Math.max(0,i-(a.originalEvent.wheelDelta||-a.originalEvent.detail*40)/3));f(o,l=i/p);f(r,-i);a.preventDefault()}).on('touchstart',function(a){j=a.originalEvent.touches[0].pageY*1.5/p;e=l}).on('touchmove',function(a){d.st(e-a.originalEvent.touches[0].pageY*1.5/p+j);a.preventDefault()})};$.fn.sb=function(){return this.each(function(){var a=$(this),b=a.data('sb');if(b)b.re();else if(!a.attr('size'))a.data('sb',new K(a))})}})();