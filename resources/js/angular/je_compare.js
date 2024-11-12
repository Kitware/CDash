/*jslint indent: 2, white: true, browser: true */
/*global jQuery
 */

// Copyright 2014 Francois Bertel
//
// Licensed under the Apache License, Version 2.0 (the "License");
// you may not use this file except in compliance with the License.
// You may obtain a copy of the License at
//
//     http://www.apache.org/licenses/LICENSE-2.0
//
// Unless required by applicable law or agreed to in writing, software
// distributed under the License is distributed on an "AS IS" BASIS,
// WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
// See the License for the specific language governing permissions and
// limitations under the License.
//
//
// jQuery plugin name: je_compare
//
// Version 1.0.0
//
// A jQuery plugin to visually compare up to four images at a glance.
//
// 
// Requirement:
//  - jquery (tested with 1.9.1, 1.11.0)
// Optional:
//  - jquery.mobile (tested with 1.3.0, 1.4.0) for 'vmousemove' event.
//
// The four image case is inspired from this webpage by Iliyan Georgiev:
// http://iliyan.com/publications/VertexMerging/comparison/
//
// Usage:
// 
// 1. Add the following line in head section of the HTML page:
// <script type="text/javascript">
// $(function () {
//  $(YOUR_CSS_SELECTOR).je_compare(YOUR_OPTION_OBJECT);
// });
// </script>
//
// 2. Add the following style in the head section of the HTML page:
// <link rel="stylesheet" href="je_compare_style.css" type="text/css" />
// keep je_compare_style.css as-is or change it to modify the thickness or
// color of the axes, or to modify the color, font and shadow of the caption.
//
// 3. In another css file, you can define a class with a background-image
// used as the background for transparent images (in the example below
// "class2").
//
// 4. In the HTML page, add a div section with up to 4 images (any extra images
//    are ignored). Use a class name that will be used by the CSS_SELECTOR
//    (in the example below "class1")
//    in the head section, and optionally another class name for the
//    background-image style defined at step 3 (in the example below "class2").
//   <div class="class1 class2">
//      <img src="image1.png" alt="image1">
//      <img src="image2.png" alt="image2">
//      <img src="image3.png" alt="image3">
//      <img src="image4.png" alt="image4">
//   </div>
//
// The option object consists of:
// - a boolean 'caption' to display the caption on each image (defined by the
// "alt" attribute). The default value is true.
// - a boolean 'orientation' to rotate the positions of the second and third
// images. The default value is false.
//   * With two images, when false, the first image is on the left, the second
//     image is on the right.
//                               IMAGE1|IMAGE2
//     When true, the first image is on the top, the second image is on the
//     bottom.
//                                   IMAGE1
//                                   ------
//                                   IMAGE2
//   * With three images, when false, the first image is on the top-left, the
//     second image is on the top-right and the third image is on the bottom.
//
//                               IMAGE1|IMAGE2
//                               -------------
//                                   IMAGE3
//
//     When true, the first image is on the top-left, the second image is on
//     the bottom-left and the third image is on the right.
//
//                               IMAGE1|
//                               ------|IMAGE3
//                               IMAGE2|
//
//   * With four images, when false, the first image is on the top-left, the
//     second image is on the top-right, the third image is on the bottom-left
//     and the fourth image on the bottom-right.
//
//                               IMAGE1|IMAGE2
//                               ------+------
//                               IMAGE3|IMAGE4
//
//     When true, the first image is on the top-left, the second image is on
//     the bottom-left, the third image is on the top-right and the fourth
//     image is on the bottom-right.
//
//                               IMAGE1|IMAGE3
//                               ------+------
//                               IMAGE2|IMAGE4
//
// - the initial position of the slider start_x, start_y as values in the [0,1]
//   interval. The default value is in the middle (0.5,0.5).
//
(function($)
{
  "use strict";
  $.fn.extend(
    {
      // Plugin name: je_compare
      // user_options
      // 
      je_compare: function(user_options)
        {
          var defaults_options,
              options,update,
              result;
          
          defaults_options=
            {
              caption:false,
              orientation:false,
              start_x:0.5,
              start_y:0.5
            };

          options=$.extend(defaults_options,user_options);
          
          // ------------------------------------------------------------------
          // Update the dimensions of the images and the positions of the
          // captions with the new size of the top-left image `new_width' and
          // `new_height'.
          update=function(that,
                          orientation,
                          new_width,
                          new_height)
            {
              var inner_size,
                  img_width,
                  img_height,
                  has_img2,
                  has_img3,
                  child1,
                  child2;
              
              img_width=that.width();
              img_height=that.height();
              has_img2=that.children('img:eq(2)').attr('alt');
              has_img3=that.children('img:eq(3)').attr('alt');
            
              // update image sizes.

              child1=that.children('.jc-layer_tl');
              if(orientation)
                {
                  child1.height(new_height);
                }
              else
                {
                  child1.width(new_width);
                }

              if(has_img2)
                {
                  child2=that.children('.jc-layer_tr');
                  if(orientation)
                    {
                      child1.width(new_width);
                      child2.width(new_width);
                    }
                  else
                    {
                      child1.height(new_height);
                      child2.height(new_height);
                    }
                }

              if(has_img3)
                {
                  child1=that.children('.jc-layer_bl');
                  if(orientation)
                    {
                      child1.height(new_height);
                    }
                  else
                    {
                      child1.width(new_width);
                  }
                }
            
              // update caption positions.

              that.children('.jc-caption_tl').css(
                {
                  bottom: img_height - new_height,
                  right: img_width - new_width
                    }
                );

              child1=that.children('.jc-caption_tr');
              if(orientation)
                {
                  child1.css(
                    {
                      top: new_height,
                      right: img_width - new_width
                        }
                    );
                }
              else
                {
                  child1.css(
                    {
                      bottom: img_height - new_height,
                      left:  new_width
                        }
                    );
                }

              child1=that.children('.jc-caption_bl');
              if(has_img3)
                {
                  if(orientation)
                    {
                      child1.css(
                        {bottom: img_height - new_height,
                            left:  new_width });
                    }
                  else
                    {
                      child1.css(
                        {top: new_height,
                            right: img_width - new_width });
                    }
                }
              else
                {
                  if(orientation)
                    {
                      inner_size=child1.innerHeight();
                      child1.css({top: new_height-inner_size*0.5,
                                     left:  new_width });
                    }
                  else
                    {
                      inner_size=child1.innerWidth();
                      child1.css(
                        {top: new_height,
                            right: img_width-inner_size*0.5 - new_width });
                    }
                }

              that.children('.jc-caption_br').css({top: new_height,
                                                   left:  new_width });
            }; // update()
          // ------------------------------------------------------------------
        
          result=this.each(
            function()
            {
              var o,
                  that,
                  imgs,
                  img0,
                  img_tl,
                  img_tr,
                  img_bl,
                  img_br,
                  cap_tl,
                  cap_tr,
                  cap_bl,
                  cap_br,
                  width,
                  height,
                  border_bottom_mask,
                  border_right_mask,
                  layers;
              
              o=options;
              that=$(this);
              
              imgs=that.children('img');
              img0=$(imgs[0]);

              // required for webkit-based browser as images arrive later 
              if(!imgs[0].complete) {
                img0.on("load", function(){
                            var im, parent, l, w, h;
                            im=$(this);
                            parent=im.parent();
                            w=im.width();
                            h=im.height();
                            parent.width(w);
                            parent.height(h);
                            l=parent.children('.jc-layer');
                            l.width(w);
                            l.height(h);
                            update(parent,o.orientation,w*o.start_x,
                                   h*o.start_y);
                          });
              }
              
              img_tl = img0.attr('src');
              img_tr = that.children('img:eq(1)').attr('src');
              img_bl = that.children('img:eq(2)').attr('src');
              img_br = that.children('img:eq(3)').attr('src');
              
              cap_tl = img0.attr('alt');
              cap_tr = that.children('img:eq(1)').attr('alt');
              cap_bl = that.children('img:eq(2)').attr('alt');
              cap_br = that.children('img:eq(3)').attr('alt');
              
              width = img0.width();
              height = img0.height();
              
              border_bottom_mask=
                {
                  borderTopWidth:   '0px',
                  borderLeftWidth:  '0px',
                  borderRightWidth: '0px'
                };
              border_right_mask=
                {
                  borderTopWidth:    '0px',
                  borderBottomWidth: '0px',
                  borderLeftWidth:   '0px'
                };
              
              that.width(width);
              that.height(height);
              
              that.children('img').hide();
              
              that.css(
                {
                  'overflow': 'hidden',
                    'position': 'relative'
                    });

              if(!img_bl) // only 2 images
                {
                  if(o.orientation)
                    {
                      that.css('cursor','row-resize');
                    }
                  else
                    {
                      that.css('cursor','col-resize');
                    }
                }
              else
                {
                  that.css('cursor','crosshair');
                }

              // for the optional transparent background image.
              that.append('<div class="jc-tbg"></div>');

              that.append('<div class="jc-layer jc-layer_tl"></div>');
              that.append('<div class="jc-layer jc-layer_tr"></div>');

              if(img_bl)
                {
                  that.append('<div class="jc-layer jc-layer_bl"></div>');
                  that.append('<div class="jc-caption jc-caption_bl">' +
                           cap_bl + '</div>');
                }
              if(img_br)
                {
                  that.append('<div class="jc-layer jc-layer_br"></div>');
                  that.append('<div class="jc-caption jc-caption_br">' +
                           cap_br + '</div>');
                }
              
              that.append('<div class="jc-caption jc-caption_tl">' + cap_tl +
                       '</div>');
              that.append('<div class="jc-caption jc-caption_tr">' + cap_tr +
                       '</div>');

              // for the optional transparent background image.
              that.children(".jc-tbg").css('position','absolute');
              that.children(".jc-tbg").css({zIndex:0,
                                         border:'0px',
                                         backgroundImage: 'inherit'});
              that.children(".jc-tbg").width(width);
              that.children(".jc-tbg").height(height);

              layers=that.children('.jc-layer').css('position','absolute');
              layers.width(width);
              layers.height(height);
              
              that.children('.jc-layer_tl').css({backgroundImage: 'url(' +
                                                 img_tl + ')',
                                                 zIndex:4});
              that.children('.jc-layer_tr').css({backgroundImage: 'url(' +
                                                 img_tr + ')',
                                                 zIndex:3});
              that.children('.jc-layer_bl').css({backgroundImage: 'url(' +
                                                 img_bl + ')',
                                                 zIndex:2});
              that.children('.jc-layer_br').css({backgroundImage: 'url(' +
                                                 img_br + ')',
                                                 zIndex:1});
              
              that.children('.jc-layer_tl').css({borderLeftWidth:'0px',
                                                 borderTopWidth:'0px'});
              if(o.orientation)
                {
                  that.children('.jc-layer_bl').css(border_bottom_mask);
                  that.children('.jc-layer_tr').css(border_right_mask);
                }
              else
                {
                  that.children('.jc-layer_tr').css(border_bottom_mask);
                  that.children('.jc-layer_bl').css(border_right_mask);
                }           
              that.children('.jc-layer_br').css({border:'0px'});
              
              // for all 4 captions.
              that.children('.jc-caption').toggle(o.caption).css(
                {zIndex: 5,
                    position: 'absolute'});
              
              update(that,o.orientation,width*o.start_x,height*o.start_y);
              
              that.bind('mousemove vmousemove',
                function(event_info)
                {
                  var current,
                    border_right_width,
                    border_bottom_width,
                    img_pos_x,
                    img_pos_y,
                    mouse_pos_x,
                    mouse_pos_y,
                    new_width,
                    new_height,
                    tl,
                    tmp1,
                    tmp2;

                  current=$(this);
                  tl=current.children('.jc-layer_tl');
                  tmp1=tl.css("borderRightWidth");
                  border_right_width=parseInt(tmp1,10);
                  tmp2=tl.css("borderBottomWidth");
                  border_bottom_width=parseInt(tmp2,10);
                  img_pos_x=current.offset().left;
                  img_pos_y=current.offset().top;
                  mouse_pos_x=event_info.pageX-border_right_width*0.5;
                  mouse_pos_y=event_info.pageY-border_bottom_width*0.5;
                  new_width=mouse_pos_x-img_pos_x;
                  new_height=mouse_pos_y-img_pos_y;
                  update(current,o.orientation,new_width,new_height);
                  event_info.preventDefault(); // to prevent scroll on mobile
                } 
                ); // mousemove vmousemove
            } 
            ); // each()
          return result;
        } // plugin function
    } // plugin object
    ); // $.fn.extend()
}(jQuery));
