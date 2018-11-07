/* eslint-env browser, jquery */
'use strict';

var $ = window.jQuery;
/**
 * Returns the url of the best choice by size
 * Best fit is smallest of sizes larger than element or biggest
 * @param  {DOM element} el     The video element
 * @param  {array} images The images array as returned by Vimeo's API
 * @return {string}        image url
 */
var getItemToFit = function getItemToFit(el, images) {
  var width = $(el).width();
  // console.log(el);

  console.log('vimeo-embed, width', width);
  return images.reduce(function (prev, curr) {
    var result = curr.width > prev.width ? curr : prev;
    if (curr.width > width && prev.width > width) {
      result = curr.width < prev.width ? curr : prev;
    }
    return result;
  });
};

$(function () {
  $('.embed-container video').each(function (i, e) {
    if (e.hasAttribute('data-pictures') && e.hasAttribute('data-files')) {
      var $e = $(e);
      var data = $e.data();

      $e.parent('.embed-container').css({
        backgroundImage: 'url(' + getItemToFit(e, data.pictures).link + ')',
        backgroundRepeat: 'no-repeat',
        backgroundSize: 'cover',
        backgroundPosition: '50%'
      });

      $e.append('<source src="' + getItemToFit(e, data.files).link + '">');
      $e.on('canplay', function (event) {
        var canPlayTime = event.target.currentTime;
        setTimeout(function () {
          if (canPlayTime === event.target.currentTime) {
            event.target.poster = getItemToFit(event.target, data.pictures).link;
            event.target.controls = true;
          }
        }, 500);
      });
      $e.on('click ended', function (event) {
        event.target.controls = true;
        event.target.muted = false;
        event.target.pause();
      });
    }
  });
});
