/**
 * Copyright (C) 2020 Tencent Cloud.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *      http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

(function ($, Drupal) {
  $(document).ready(function(){
    var button = $("input[name='send_code']");
    button.attr('type', 'button');
    //倒计时
    var waitTime = 60;
    function sendCountdown() {
      if (waitTime > 0) {
        button.val(waitTime + '秒后重新获取验证码').attr("disabled", true);
        waitTime--;
        setTimeout(sendCountdown, 1000);
      } else {
        button.val('获取短信验证码').attr("disabled", false).fadeTo("slow", 1);
        waitTime = 60;
      }
    }

    $.fn.sendCodeCallback = function(response) {
      console.log(response);
      if (response.code !== 0) {
        alert(response.msg);
        return;
      }
      sendCountdown();
    };
  });

})(jQuery, Drupal);
