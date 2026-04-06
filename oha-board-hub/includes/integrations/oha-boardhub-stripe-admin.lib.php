<?php
/* OHA Board Hub – Stripe Admin (embedded lib) */

if (!defined('ABSPATH')) exit;

class OHA_BoardHub_Stripe_Admin {
  const OPT = 'oha_sa_settings';
  const CAP = 'manage_oha_members';

  public function __construct() {
    add_action('admin_init', [$this,'ensure_caps']);
    // Fully disabled - using consolidated Board Hub settings instead
    // add_action('admin_init', [$this,'register_settings']);
    add_action('rest_api_init', [$this,'register_rest']);
    add_shortcode('oha_board_stripe_admin', [$this,'shortcode']);
    add_action('wp_head', [$this,'inline_styles']);
  }

  function ensure_caps() {
    // Add manage_oha_members capability to administrators
    $admin_role = get_role('administrator');
    if ($admin_role && !$admin_role->has_cap(self::CAP)) {
        $admin_role->add_cap(self::CAP);
    }
    
    // CRITICAL FIX: Also add to oha_board role so board members can save Stripe settings
    $board_role = get_role('oha_board');
    if ($board_role && !$board_role->has_cap(self::CAP)) {
        $board_role->add_cap(self::CAP);
    }
  }

  function settings_menu() {
    add_options_page('OHA Stripe Admin', 'OHA Stripe Admin', 'manage_options', 'oha-sa', [$this,'settings_page']);
  }

  function register_settings() {
    register_setting(self::OPT, self::OPT);
    add_settings_section('main','Stripe Admin Settings', function(){
      echo '<p>Configure Stripe settings. Add shortcode <code>[oha_board_stripe_admin]</code> to your Board Hub tab.</p>';
    }, self::OPT);

    $fields = [
      'secret_key'       => 'Stripe Secret Key (sk_...)',
      'portal_return_url'=> 'Customer Portal Return URL',
      'allowed_prices'   => 'Allowed Price IDs (comma-separated for Renew/Swap)',
      'success_url'      => 'Checkout Success URL (use {CHECKOUT_SESSION_ID})',
      'cancel_url'       => 'Checkout Cancel URL',
    ];
    foreach ($fields as $key=>$label) {
      add_settings_field($key, $label, function() use ($key){
        $opt = get_option(self::OPT, []);
        $val = isset($opt[$key]) ? $opt[$key] : '';
        $type = ($key === 'secret_key') ? 'password' : 'text';
        printf('<input type="%s" name="%s[%s]" value="%s" class="regular-text" />',
          esc_attr($type), esc_attr(self::OPT), esc_attr($key), esc_attr($val));
      }, self::OPT, 'main');
    }
  }

  function settings_page() { ?>
    <div class="wrap">
      <h1>OHA Stripe Admin</h1>
      <form method="post" action="options.php">
        <?php settings_fields(self::OPT); do_settings_sections(self::OPT); submit_button(); ?>
      </form>
      <p><strong>Shortcode:</strong> <code>[oha_board_stripe_admin]</code></p>
    </div>
  <?php }

  function inline_styles() { ?>
    <style>
      .oha-sa-wrap{background:#0f0f10;color:#eaeaea;padding:16px;border-radius:12px;margin:8px 0}
      .oha-sa-wrap h2{margin:0 0 8px}
      .oha-sa-search{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px}
      .oha-sa-search input[type=text]{flex:1;min-width:260px;padding:8px;border-radius:8px;border:1px solid #333;background:#1a1a1a;color:#fff}
      .oha-sa-card{border:1px solid #2a2a2a;border-radius:10px;padding:12px;margin:12px 0;background:#141414}
      .oha-sa-h3{font-size:18px;font-weight:600;margin:0 0 6px}
      .oha-sa-row{display:flex;flex-wrap:wrap;gap:12px;align-items:center;font-size:14px;opacity:.95}
      .oha-sa-actions{display:flex;flex-wrap:wrap;gap:8px;margin-top:8px}
      .oha-sa-subbox{border:1px dashed #333;border-radius:8px;padding:8px;margin-top:10px;background:#101010}
      .oha-sa-wrap a,.oha-sa-wrap a:visited{color:#7dc7ff;text-decoration:underline}
      .oha-sa-wrap .button{background:#fff;color:#111;border-color:#eee}
      .oha-sa-wrap .button:hover{background:#f3f3f3;color:#000}
    </style>
  <?php }

  function shortcode($atts, $content='') {
    if (!is_user_logged_in() || !current_user_can(self::CAP)) {
      return '<div class="notice notice-error"><p>No permission.</p></div>';
    }
    $nonce = wp_create_nonce('wp_rest');
    ob_start(); ?>
    <div class="oha-sa-wrap">
      <h2>Membership Admin</h2>
      <form class="oha-sa-search">
        <input type="text" name="q" placeholder="Search by email, name, or cus_... ID" />
        <button type="submit" class="button button-primary">Search</button>
      </form>
      <div class="oha-sa-results">Enter an email, name, or Stripe customer ID.</div>
    </div>
    <script>
    (function(){
      const wrap = document.currentScript.previousElementSibling;
      const form = wrap.querySelector('.oha-sa-search');
      const out  = wrap.querySelector('.oha-sa-results');
      form.addEventListener('submit', async (e)=>{
        e.preventDefault();
        out.textContent = 'Searching…';
        const fd = new FormData(form);
        const res = await fetch('<?php echo esc_url( rest_url('oha/v1/sa/search') ); ?>', {
          method:'POST', headers:{'X-WP-Nonce':'<?php echo esc_js($nonce); ?>'}, body: fd
        });
        const data = await res.json();
        out.innerHTML = data.html || ('<div class="notice notice-error"><p>'+ (data.error||'No results') +'</p></div>');
        wireButtons();
      });
      function wireButtons(){
        out.querySelectorAll('[data-action]').forEach(btn => {
          btn.addEventListener('click', async () => {
            const action = btn.dataset.action;
            const params = JSON.parse(btn.dataset.params||'{}');
            btn.disabled = true; const old = btn.textContent; btn.textContent = 'Working…';
            const fd = new FormData(); fd.append('action', action); fd.append('params', JSON.stringify(params));
            const res = await fetch('<?php echo esc_url( rest_url('oha/v1/sa/action') ); ?>', {
              method:'POST', headers:{'X-WP-Nonce':'<?php echo esc_js($nonce); ?>'}, body: fd
            });
            const data = await res.json();
            btn.disabled = false; btn.textContent = old;
            if (data.message) alert(data.message);
            if (data.refresh_html) out.innerHTML = data.refresh_html, wireButtons();
          });
        });
      }
    })();
    </script>
    <?php
    return ob_get_clean();
  }

  function register_rest() {
    register_rest_route('oha/v1','/sa/search',[
      'methods' => 'POST',
      'callback'=> [$this,'api_search'],
      'permission_callback' => function(){ return current_user_can(self::CAP); }
    ]);
    register_rest_route('oha/v1','/sa/action',[
      'methods' => 'POST',
      'callback'=> [$this,'api_action'],
      'permission_callback' => function(){ return current_user_can(self::CAP); }
    ]);
  }

  function get_settings() {
    $opt = get_option(self::OPT, []);
    $defaults = [
      'secret_key'        => '',
      'portal_return_url' => home_url('/'),
      'allowed_prices'    => '',
      'success_url'       => home_url('/membership/success?session_id={CHECKOUT_SESSION_ID}'),
      'cancel_url'        => home_url('/membership/cancelled'),
    ];
    return wp_parse_args($opt, $defaults);
  }

  function stripe_request($method, $path, $params = []) {
    $s = $this->get_settings();
    $sk = $s['secret_key'];
    if (!$sk) return new WP_Error('stripe_missing_key', 'Secret key not configured.');
    $url = 'https://api.stripe.com' . $path;
    $args = ['method'=>$method,'headers'=>['Authorization'=>'Bearer '.$sk],'timeout'=>20];
    if (strtoupper($method)==='GET') {
      if (!empty($params)) $url = add_query_arg($params, $url);
    } else {
      $args['headers']['Content-Type']='application/x-www-form-urlencoded';
      $args['body']=$this->http_build_query_deep($params);
    }
    $res = wp_remote_request($url, $args);
    if (is_wp_error($res)) return $res;
    $code = wp_remote_retrieve_response_code($res);
    $body = json_decode(wp_remote_retrieve_body($res), true);
    if ($code<200||$code>=300) return new WP_Error('stripe_api_error', $body['error']['message']??'Stripe error');
    return $body;
  }

  private function http_build_query_deep($params, $prefix = '') {
    $out = [];
    foreach ($params as $k => $v) {
      $key = $prefix ? "{$prefix}[{$k}]" : $k;
      if (is_array($v)) {
        $out[] = $this->http_build_query_deep($v, $key);
      } else {
        $out[] = rawurlencode($key) . '=' . rawurlencode($v);
      }
    }
    return implode('&', $out);
  }

  /* ---------------- API: search + actions ---------------- */

  function api_search(WP_REST_Request $r) {
    $q = sanitize_text_field($r->get_param('q'));
    if (!$q) return ['error'=>'Enter query'];
    if (preg_match('#^cus_[A-Za-z0-9]+$#',$q)) {
      $cust=$this->stripe_request('GET','/v1/customers/'.$q);
      if (is_wp_error($cust)) return ['error'=>$cust->get_error_message()];
      $customers=[$cust];
    } else {
      $list=$this->stripe_request('GET','/v1/customers',['limit'=>50]);
      if (is_wp_error($list)) return ['error'=>$list->get_error_message()];
      $customers=array_values(array_filter($list['data']??[],function($c)use($q){
        $hay=strtolower(($c['email']??'').' '.($c['name']??''));
        return strpos($hay,strtolower($q))!==false;
      }));
    }
    if (!$customers) return ['html'=>'<p>No customers found.</p>'];

    ob_start();
    foreach($customers as $c){
      $cid = isset($c['id']) ? $c['id'] : '';
      $name = isset($c['name']) ? $c['name'] : (isset($c['email']) ? $c['email'] : $cid);
      $email = isset($c['email']) ? $c['email'] : '';
      $dashboard_url = 'https://dashboard.stripe.com/customers/' . $cid;
      $params = esc_attr(json_encode(['customer_id'=>$cid]));
      echo '<div class="oha-sa-card">';
      echo '<div class="oha-sa-h3">'.esc_html($name).'</div>';
      echo '<div class="oha-sa-row"><strong>Customer:</strong> '.esc_html($cid).'</div>';
      if ($email) echo '<div class="oha-sa-row"><strong>Email:</strong> <a href="mailto:'.esc_attr($email).'">'.esc_html($email).'</a></div>';
      echo '<div class="oha-sa-actions">';
      echo '<button class="button" data-action="portal" data-params="'.$params.'">Open Portal</button> ';
      echo '<a class="button" href="'.esc_url($dashboard_url).'" target="_blank" rel="noopener">Open in Stripe</a> ';
      echo '</div>';
      // Subscriptions
      echo $this->render_subscriptions_for_customer($cid);
      echo '</div>';
    }
    return ['html'=>ob_get_clean()];
  }

  private function render_subscriptions_for_customer($customer_id){
    $list = $this->stripe_request('GET','/v1/subscriptions',['limit'=>20,'customer'=>$customer_id,'status'=>'all']);
    if (is_wp_error($list)) {
      return '<div class="notice notice-error"><p>'.esc_html($list->get_error_message()).'</p></div>';
    }
    $subs = $list['data'] ?? [];
    ob_start();
    if (!$subs){
      $params = esc_attr(json_encode(['customer_id'=>$customer_id]));
      echo '<div class="oha-sa-subbox"><div><strong>No active subscriptions.</strong></div>';
      echo '<button class="button button-primary" data-action="renew_checkout" data-params="'.$params.'">Create Renew Checkout</button>';
      echo '</div>';
    } else {
      foreach($subs as $s){
        $sid = $s['id'];
        $status = $s['status'] ?? '';
        $period_end = !empty($s['current_period_end']) ? date('Y-m-d', intval($s['current_period_end'])) : '';
        $items = $s['items']['data'] ?? [];
        $price = (!empty($items) && !empty($items[0]['price'])) ? $items[0]['price'] : null;
        $pname = '';
        if ($price) {
          if (!empty($price['nickname'])) $pname = $price['nickname'];
          elseif (!empty($price['id'])) $pname = $price['id'];
        }
        $pname_safe = esc_html($pname ? $pname : $sid);
        echo '<div class="oha-sa-subbox">';
        echo '<div><strong>'.$pname_safe.'</strong></div>';
        echo '<div>Status: <code>'.esc_html($status).'</code> | Renews/ends: '.esc_html($period_end).'</div>';
        $params_sub = esc_attr(json_encode(['subscription_id'=>$sid]));
        echo '<div class="oha-sa-actions">';
        echo '<button class="button" data-action="cancel" data-params="'.$params_sub.'">Cancel at period end</button> ';
        echo '<button class="button" data-action="resume" data-params="'.$params_sub.'">Resume</button> ';
        echo '<button class="button" data-action="swap" data-params="'.$params_sub.'">Swap Price</button> ';
        echo '</div>';
        echo '</div>';
      }
    }
    return ob_get_clean();
  }

  function api_action(WP_REST_Request $r){
    $action=sanitize_text_field($r->get_param('action'));
    $params=json_decode($r->get_param('params')?:'{}',true);

    if($action==='portal'){
      $cid=sanitize_text_field($params['customer_id']??'');
      $s=$this->get_settings();
      $body=$this->stripe_request('POST','/v1/billing_portal/sessions',['customer'=>$cid,'return_url'=>$s['portal_return_url']]);
      if(is_wp_error($body)) return ['error'=>$body->get_error_message()];
      return ['ok'=>true,'message'=>'Portal: '.$body['url']];
    }

    if($action==='cancel'){
      $sub_id=sanitize_text_field($params['subscription_id']??'');
      $body=$this->stripe_request('POST','/v1/subscriptions/'.$sub_id,['cancel_at_period_end'=>'true']);
      if(is_wp_error($body)) return ['error'=>$body->get_error_message()];
      return ['ok'=>true,'message'=>'Will cancel at period end.','refresh_html'=>$this->render_subscriptions_for_customer($body['customer'])];
    }

    if($action==='resume'){
      $sub_id=sanitize_text_field($params['subscription_id']??'');
      $body=$this->stripe_request('POST','/v1/subscriptions/'.$sub_id,['cancel_at_period_end'=>'false']);
      if(is_wp_error($body)) return ['error'=>$body->get_error_message()];
      return ['ok'=>true,'message'=>'Resumed (cancel_at_period_end removed).','refresh_html'=>$this->render_subscriptions_for_customer($body['customer'])];
    }

    if($action==='swap'){
      $sub_id=sanitize_text_field($params['subscription_id']??'');
      $sub=$this->stripe_request('GET','/v1/subscriptions/'.$sub_id);
      if(is_wp_error($sub)) return ['error'=>$sub->get_error_message()];
      $items = $sub['items']['data'] ?? [];
      if(!$items) return ['error'=>'No items on subscription.'];
      $item_id = $items[0]['id'];
      $allowed = array_filter(array_map('trim', explode(',', $this->get_settings()['allowed_prices'])));
      if(!$allowed) return ['error'=>'No allowed Price IDs configured in settings.'];
      $new_price = $allowed[0];
      $body=$this->stripe_request('POST','/v1/subscriptions/'.$sub_id,[
        'cancel_at_period_end'=>'false',
        'proration_behavior'=>'create_prorations',
        'items'=>[['id'=>$item_id,'price'=>$new_price]]
      ]);
      if(is_wp_error($body)) return ['error'=>$body->get_error_message()];
      return ['ok'=>true,'message'=>'Swapped to '.$new_price,'refresh_html'=>$this->render_subscriptions_for_customer($body['customer'])];
    }

    if($action==='renew_checkout'){
      $customer_id=sanitize_text_field($params['customer_id']??'');
      $s=$this->get_settings();
      $allowed = array_filter(array_map('trim', explode(',', $s['allowed_prices'])));
      if(!$allowed) return ['error'=>'No allowed Price IDs configured in settings.'];
      $price_id = $allowed[0];
      $sess=$this->stripe_request('POST','/v1/checkout/sessions',[
        'mode'=>'subscription',
        'customer'=>$customer_id,
        'line_items'=>[['price'=>$price_id,'quantity'=>1]],
        'success_url'=>$s['success_url'],
        'cancel_url'=>$s['cancel_url'],
        'allow_promotion_codes'=>'true'
      ]);
      if(is_wp_error($sess)) return ['error'=>$sess->get_error_message()];
      return ['ok'=>true,'message'=>'Renew Checkout: '.$sess['url']];
    }

    return ['error'=>'Unknown action'];
  }
}
new OHA_BoardHub_Stripe_Admin();
