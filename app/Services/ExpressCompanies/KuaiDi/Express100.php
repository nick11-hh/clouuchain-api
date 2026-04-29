<?php

namespace App\Services\ExpressCompanies\KuaiDi;

use App\Models\ApiTrackingConfig;
use App\Services\ExpressCompanies\KuaiDi\Exceptions\HttpException;
use App\Services\ExpressCompanies\KuaiDi\Exceptions\InvalidArgumentException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class Express100
{
    protected $api = 'https://poll.kuaidi100.com/poll/query.do';
    protected $app_id;
    protected $app_key;
    protected $guzzleOptions = [];

    /**
     * Kuaidi100 constructor.
     * @param $app_id
     * @param $app_key
     */
    public function __construct($app_id, $app_key)
    {
        $this->app_id = $app_id;
        $this->app_key = $app_key;
    }

    /**
     * 快递查询
     * @param string $number 快递单号
     * @param string $code 物流公司单号
     * @param string $phone
     * @return string
     * @throws InvalidArgumentException
     * @throws HttpException
     */
    public function track(string $number = '', string $code = '', string $phone = '')
    {
        if (empty($number)) {
            throw new InvalidArgumentException('TrackingCode is required');
        }

        if (empty($code)) {
            $code = $this->autoNumber($number);

            if (! $code) {
                throw new InvalidArgumentException('ShippingCode is required');
            }
        }

        $allow_shipping_code = ['youzhengguonei', 'youzhengguoji', 'ems', 'emsguoji', 'emsinten', 'bjemstckj', 'shunfeng', 'shentong', 'yuantong', 'zhongtong', 'huitongkuaidi', 'yunda', 'zhaijisong', 'tiantian', 'debangwuliu', 'guotongkuaidi', 'zengyisudi', 'suer', 'ztky', 'zhongtiewuliu', 'ganzhongnengda', 'youshuwuliu', 'quanfengkuaidi', 'jd', 'fedex', 'fedexus', 'dhlen', 'dhl', 'dhlde', 'tnten', 'tnt', 'upsen', 'ups', 'usps', 'dpd', 'dpdgermany', 'dpdpoland', 'dpduk', 'gls', 'dpexen', 'tollpriority', 'aramex', 'dpex', 'zhaijibian', 'yamato', 'hkpost', 'parcelforce', 'royalmail', 'auspost', 'canpost', 'yitongfeihong', 'rufengda', 'haihongwangsong', 'tonghetianxia', 'zhengzhoujianhua', 'sxhongmajia', 'zhimakaimen', 'lejiedi', 'lijisong', 'yinjiesudi', 'menduimen', 'hebeijianhua', 'weitepai', 'fengxingtianxia', 'shangcheng', 'neweggozzo', 'xinhongyukuaidi', 'quanyikuaidi', 'biaojikuaidi', 'xingchengjibian', 'yafengsudi', 'yuanweifeng', 'quanritongkuaidi', 'anxindakuaixi', 'minghangkuaidi', 'fenghuangkuaidi', 'jinguangsudikuaijian', 'peisihuoyunkuaidi', 'aae', 'datianwuliu', 'xinbangwuliu', 'longbanwuliu', 'yibangwuliu', 'lianhaowuliu', 'guangdongyouzhengwuliu', 'zhongyouwuliu', 'tiandihuayu', 'shenghuiwuliu', 'changyuwuliu', 'feikangda', 'yuanzhijiecheng', 'wanjiawuliu', 'yuanchengwuliu', 'xinfengwuliu', 'wenjiesudi', 'quanchenkuaidi', 'jiayiwuliu', 'kuaijiesudi', 'dsukuaidi', 'quanjitong', 'anjiekuaidi', 'yuefengwuliu', 'jixianda', 'baifudongfang', 'bht', 'wuyuansudi', 'lanbiaokuaidi', 'coe', 'nanjing', 'hengluwuliu', 'jindawuliu', 'huaxialongwuliu', 'yuntongkuaidi', 'jiajiwuliu', 'shengfengwuliu', 'yuananda', 'jiayunmeiwuliu', 'wanxiangwuliu', 'hongpinwuliu', 'shangda', 'zhongtiewuliu', 'yuanfeihangwuliu', 'haiwaihuanqiu', 'santaisudi', 'jinyuekuaidi', 'lianbangkuaidi', 'feikuaida', 'zhongxinda', 'gongsuda', 'jialidatong', 'ocs', 'meiguokuaidi', 'lijisong', 'disifang', 'kangliwuliu', 'kuayue', 'haimengsudi', 'shenganwuliu', 'zhongsukuaidi', 'ontrac', 'sevendays', 'mingliangwuliu', 'vancl', 'huaqikuaiyun', 'city100', 'suijiawuliu', 'feibaokuaidi', 'chuanxiwuliu', 'jietekuaidi', 'longlangkuaidi', 'emsen', 'zhongtianwanyun', 'bangsongwuliu', 'auspost', 'canpost', 'canpostfr', 'shunfengen', 'huiqiangkuaidi', 'xiyoutekuaidi', 'haoshengwuliu', 'yilingsuyun', 'dayangwuliu', 'didasuyun', 'yitongda', 'youbijia', 'yishunhang', 'feihukuaidi', 'xiaoxiangchenbao', 'balunzhi', 'minshengkuaidi', 'syjiahuier', 'minbangsudi', 'shanghaikuaitong', 'xiaohongmao', 'gsm', 'annengwuliu', 'kcs', 'citylink', 'diantongkuaidi', 'fanyukuaidi', 'pingandatengfei', 'guangdongtonglu', 'zhongruisudi', 'kuaidawuliu', 'adp', 'fardarww', 'fandaguoji', 'shlindao', 'sinoex', 'zhongwaiyun', 'dechuangwuliu', 'ldxpres', 'ruidianyouzheng', 'postenab', 'nuoyaao', 'xianglongyuntong', 'pinsuxinda', 'yuxinwuliu', 'peixingwuliu', 'hutongwuliu', 'xianchengliansudi', 'yujiawuliu', 'yiqiguojiwuliu', 'fedexcn', 'lianbangkuaidien', 'zhongtongphone', 'saiaodimmb', 'shanghaiwujiangmmb', 'singpost', 'yinsu', 'ndwl', 'sucheng', 'chuangyi', 'dianyi', 'cqxingcheng', 'scxingcheng', 'gzxingcheng', 'ytkd', 'gatien', 'gaticn', 'jcex', 'peex', 'kxda', 'advancing', 'huiwen', 'yxexpress', 'donghong', 'feiyuanvipshop', 'hlyex', 'kuaiyouda', 'riyuwuliu', 'sutongwuliu', 'nanjingshengbang', 'anposten', 'japanposten', 'postdanmarken', 'brazilposten', 'postnlcn', 'postnl', 'emsukrainecn', 'emsukraine', 'ukrpostcn', 'ukrpost', 'haihongmmb', 'fedexuk', 'fedexukcn', 'dingdong', 'upsfreight', 'abf', 'purolator', 'bpost', 'bpostinter', 'lasership', 'yodel', 'dhlnetherlands', 'myhermes', 'fastway', 'chronopostfra', 'selektvracht', 'lanhukuaidi', 'belgiumpost', 'upsmailinno', 'postennorge', 'swisspostcn', 'swisspost', 'royalmailcn', 'dhlbenelux', 'novaposhta', 'dhlpoland', 'estes', 'tntuk', 'deltec', 'opek', 'italysad', 'mrw', 'chronopostport', 'correosdees', 'directlink', 'eltahell', 'ceskaposta', 'siodemka', 'seur', 'jiuyicn', 'hrvatska', 'bulgarian', 'portugalseur', 'ecfirstclass', 'dtdcindia', 'safexpress', 'koreapost', 'tntau', 'thailand', 'skynetmalaysia', 'malaysiapost', 'malaysiaems', 'saudipost', 'southafrican', 'ocaargen', 'nigerianpost', 'chile', 'israelpost', 'estafeta', 'gdkd', 'mexico', 'romanian', 'tntitaly', 'multipack', 'portugalctt', 'interlink', 'hzpl', 'gatikwe', 'redexpress', 'mexicodenda', 'tcixps', 'hre', 'speedpost', 'emsinten', 'asendiausa', 'chronopostfren', 'italiane', 'gda', 'chukou1', 'huangmajia', 'anlexpress', 'shipgce', 'xlobo', 'emirates', 'nsf', 'pakistan', 'shiyunkuaidi', 'ucs', 'afghan', 'belpost', 'quantwl', 'efs', 'tntpostcn', 'gml', 'gtongsudi', 'donghanwl', 'rpx', 'rrs', 'htongexpress', 'kyrgyzpost', 'latvia', 'libanpost', 'lithuania', 'maldives', 'malta', 'macedonia', 'newzealand', 'moldova', 'bangladesh', 'serbia', 'cypruspost', 'tunisia', 'uzbekistan', 'caledonia', 'republic', 'haypost', 'yemen', 'india', 'england', 'jordan', 'vietnam', 'montenegro', 'correos', 'xilaikd', 'greenland', 'phlpost', 'ecuador', 'iceland', 'emonitoring', 'albania', 'aruba', 'egypt', 'omniva', 'leopard', 'sinoairinex', 'hyk', 'ckeex', 'hungary', 'macao', 'postserv', 'kuaitao', 'peru', 'indonesia', 'kazpost', 'lbbk', 'bqcwl', 'pfcexpress', 'csuivi', 'austria', 'ukraine', 'uganda', 'azerbaijan', 'finland', 'slovak', 'ethiopia', 'luxembourg', 'mauritius', 'brunei', 'quantium', 'tanzania', 'oman', 'gibraltar', 'byht', 'vnpost', 'anxl', 'dfpost', 'huoban', 'tianzong', 'bohei', 'bolivia', 'cambodia', 'bahrain', 'namibia', 'rwanda', 'lesotho', 'kenya', 'cameroon', 'belize', 'paraguay', 'sfift', 'hnfy', 'iparcel', 'bjxsrd', 'mailikuaidi', 'rfsd', 'letseml', 'cnpex', 'xsrd', 'chinatzx', 'qbexpress', 'idada', 'skynet', 'nedahm', 'czwlyn', 'wanboex', 'nntengda', 'sujievip', 'gotoubi', 'ecmsglobal', 'fastgo', 'ecmscn', 'eshunda', 'suteng', 'gdxp', 'yundaexus', 'szdpex', 'baishiwuliu', 'postnlpacle', 'ltexp', 'ztong', 'xtb', 'airpak', 'postnlchina', 'colissimo', 'pcaexpress', 'hanrun', 'cosco', 'sundarexpress', 'ajexpress', 'arkexpress', 'adaexpress', 'changjiang', 'bdatong', 'stoexpress', 'epanex', 'shunjiefengda', 'nmhuahe', 'deutschepost', 'baitengwuliu', 'pjbest', 'quansutong', 'zhongjiwuliu', 'jiuyescm', 'tykd', 'dabei', 'chengji', 'chengguangkuaidi', 'sagawa', 'lantiankuaidi', 'yongchangwuliu', 'birdex', 'yizhengdasuyun', 'sdyoupei', 'trakpak', 'gts', 'aolau', 'yiex', 'tongdaxing', 'hkposten', 'flysman', 'zhuanyunsifang', 'ilogen', 'dongjun', 'japanpost', 'jiajiatong56', 'jrypex', 'xaetc', 'doortodoor', 'xintianjie', 'sd138', 'hjs', 'quanxintong', 'amusorder', 'junfengguoji', 'kingfreight', 'subida', 'sucmj', 'yamaxunwuliu', 'jinchengwuliu', 'jgwl', 'yufeng', 'zhichengtongda', 'rytsd', 'hangyu', 'pzhjst', 'yousutongda', 'qinyuan', 'auexpress', 'zhdwl', 'fbkd', 'huada', 'fox', 'huanqiu', 'huilian', 'a2u', 'ueq', 'scic', 'yidatong', 'ruexp', 'htwd', 'speedoex', 'lianyun', 'jieanda', 'shlexp', 'ewe', 'abcglobal', 'mangguo', 'goldhaitao', 'jiguang', 'ftd', 'dcs', 'chengda', 'zhonghuan', 'shunbang', 'qichen', 'auex', 'aosu', 'aus', 'tianma', 'mjexp', 'chunfai', 'zenzen', 'mxe56', 'hipito', 'pengcheng', 'guanting', 'jinan', 'haidaibao', 'cllexpress', 'banma', 'youjia', 'buytong', 'xingyuankuaidi', 'quansu', 'sunjex', 'lutong', 'xynyc', 'xiaocex', 'airgtc', 'dindon', 'hqtd', 'haoyoukuai', 'yongwangda', 'mchy', 'flyway', 'qzx56', 'bsht', 'ilyang', 'xianfeng', 'timedg', 'meiquick', 'tny', 'valueway', 'sunspeedy', 'bphchina', 'yingchao', 'correoargentino', 'vanuatu', 'barbados', 'samoa', 'fiji', 'edlogistics', 'esinotrans', 'kuachangwuliu', 'cnausu', 'gslhkd', 'ccd', 'benteng', 'mapleexpress', 'topspeedex', 'yjxlm', 'otobv', 'jmjss', 'onehcang', 'hfwuxi', 'wtdchina', 'shunjieda', 'qskdyxgs', 'tlky', 'cloudexpress', 'speeda', 'zhongtongguoji', 'xipost', 'nle', 'nlebv', 'stkd', 'sinatone', 'auod', 'ahdf', 'wzhaunyun', 'lntjs', 'iexpress', 'bcwelt', 'euasia', 'ycgky', 'ledii', 'gswtkd', 'zyzoom', 'globaltracktrace', 'sendtochina', 'exfresh', 'emsluqu', 'sccod', 'runhengfeng', 'hkems', 'skypost', 'ruidaex', 'decnlh', 'suning', 'nzzto', 'lmfex', 'lineone', 'sihaiet', 'wotu', 'dekuncn', 'zsky123', 'hongjie', 'hongxun', 'ahkbps'];

        if (!in_array($code, $allow_shipping_code)) {
            throw new InvalidArgumentException('Current ShippingCode is not support');
        }

        if ($code == 'shunfeng' && empty($phone)) {
            throw new InvalidArgumentException('This Order Need PhoneNumber');
        }

        $post['customer'] = $this->app_id;
        $data = [
            'com' => $code,
            'num' => $number
        ];

        if (!empty($phone)) {
            $data['mobiletelephone'] = $phone;
        }

        $post['param'] = json_encode($data);
        $post['sign'] = strtoupper(md5($post['param'] . $this->app_key . $post['customer']));

        try {
            $response = $this->getHttpClient()->request('POST', $this->api, [
                'form_params' => $post
            ])->getBody()->getContents();
        } catch (GuzzleException $e) {
            throw new HttpException($e->getMessage(), $e->getCode(), $e);
        }

        return $response;
    }

    /**
     * @param string $sn
     * @return mixed|string
     * @throws HttpException
     */
    public function autoNumber(string $sn)
    {
        try {
            $response = $this->getHttpClient()->request('POST', 'http://www.kuaidi100.com/autonumber/auto', [
                'query' => [
                    'num' => $sn,
                    'key' => $this->app_key,
                ],
            ])->getBody()->getContents();
        } catch (GuzzleException $e) {
            throw new HttpException($e->getMessage(), $e->getCode(), $e);
        }

        $response = json_decode($response, true);

        info('当前单号判断返回', $response);

        if (!$response || !empty($response['returnCode'])) {
            return '';
        }

        return $response[0]['comCode'];
    }

    public function subscribe(int $companyId, string $number = '', string $code = '', string $phone = '')
    {
        if (empty($number)) {
            throw new InvalidArgumentException('TrackingCode is required');
        }

        if (empty($code)) {
            $code = $this->autoNumber($number);

            if (! $code) {
                throw new InvalidArgumentException('ShippingCode is required');
            }
        }

        $allow_shipping_code = ['youzhengguonei', 'youzhengguoji', 'ems', 'emsguoji', 'emsinten', 'bjemstckj', 'shunfeng', 'shentong', 'yuantong', 'zhongtong', 'huitongkuaidi', 'yunda', 'zhaijisong', 'tiantian', 'debangwuliu', 'guotongkuaidi', 'zengyisudi', 'suer', 'ztky', 'zhongtiewuliu', 'ganzhongnengda', 'youshuwuliu', 'quanfengkuaidi', 'jd', 'fedex', 'fedexus', 'dhlen', 'dhl', 'dhlde', 'tnten', 'tnt', 'upsen', 'ups', 'usps', 'dpd', 'dpdgermany', 'dpdpoland', 'dpduk', 'gls', 'dpexen', 'tollpriority', 'aramex', 'dpex', 'zhaijibian', 'yamato', 'hkpost', 'parcelforce', 'royalmail', 'auspost', 'canpost', 'yitongfeihong', 'rufengda', 'haihongwangsong', 'tonghetianxia', 'zhengzhoujianhua', 'sxhongmajia', 'zhimakaimen', 'lejiedi', 'lijisong', 'yinjiesudi', 'menduimen', 'hebeijianhua', 'weitepai', 'fengxingtianxia', 'shangcheng', 'neweggozzo', 'xinhongyukuaidi', 'quanyikuaidi', 'biaojikuaidi', 'xingchengjibian', 'yafengsudi', 'yuanweifeng', 'quanritongkuaidi', 'anxindakuaixi', 'minghangkuaidi', 'fenghuangkuaidi', 'jinguangsudikuaijian', 'peisihuoyunkuaidi', 'aae', 'datianwuliu', 'xinbangwuliu', 'longbanwuliu', 'yibangwuliu', 'lianhaowuliu', 'guangdongyouzhengwuliu', 'zhongyouwuliu', 'tiandihuayu', 'shenghuiwuliu', 'changyuwuliu', 'feikangda', 'yuanzhijiecheng', 'wanjiawuliu', 'yuanchengwuliu', 'xinfengwuliu', 'wenjiesudi', 'quanchenkuaidi', 'jiayiwuliu', 'kuaijiesudi', 'dsukuaidi', 'quanjitong', 'anjiekuaidi', 'yuefengwuliu', 'jixianda', 'baifudongfang', 'bht', 'wuyuansudi', 'lanbiaokuaidi', 'coe', 'nanjing', 'hengluwuliu', 'jindawuliu', 'huaxialongwuliu', 'yuntongkuaidi', 'jiajiwuliu', 'shengfengwuliu', 'yuananda', 'jiayunmeiwuliu', 'wanxiangwuliu', 'hongpinwuliu', 'shangda', 'zhongtiewuliu', 'yuanfeihangwuliu', 'haiwaihuanqiu', 'santaisudi', 'jinyuekuaidi', 'lianbangkuaidi', 'feikuaida', 'zhongxinda', 'gongsuda', 'jialidatong', 'ocs', 'meiguokuaidi', 'lijisong', 'disifang', 'kangliwuliu', 'kuayue', 'haimengsudi', 'shenganwuliu', 'zhongsukuaidi', 'ontrac', 'sevendays', 'mingliangwuliu', 'vancl', 'huaqikuaiyun', 'city100', 'suijiawuliu', 'feibaokuaidi', 'chuanxiwuliu', 'jietekuaidi', 'longlangkuaidi', 'emsen', 'zhongtianwanyun', 'bangsongwuliu', 'auspost', 'canpost', 'canpostfr', 'shunfengen', 'huiqiangkuaidi', 'xiyoutekuaidi', 'haoshengwuliu', 'yilingsuyun', 'dayangwuliu', 'didasuyun', 'yitongda', 'youbijia', 'yishunhang', 'feihukuaidi', 'xiaoxiangchenbao', 'balunzhi', 'minshengkuaidi', 'syjiahuier', 'minbangsudi', 'shanghaikuaitong', 'xiaohongmao', 'gsm', 'annengwuliu', 'kcs', 'citylink', 'diantongkuaidi', 'fanyukuaidi', 'pingandatengfei', 'guangdongtonglu', 'zhongruisudi', 'kuaidawuliu', 'adp', 'fardarww', 'fandaguoji', 'shlindao', 'sinoex', 'zhongwaiyun', 'dechuangwuliu', 'ldxpres', 'ruidianyouzheng', 'postenab', 'nuoyaao', 'xianglongyuntong', 'pinsuxinda', 'yuxinwuliu', 'peixingwuliu', 'hutongwuliu', 'xianchengliansudi', 'yujiawuliu', 'yiqiguojiwuliu', 'fedexcn', 'lianbangkuaidien', 'zhongtongphone', 'saiaodimmb', 'shanghaiwujiangmmb', 'singpost', 'yinsu', 'ndwl', 'sucheng', 'chuangyi', 'dianyi', 'cqxingcheng', 'scxingcheng', 'gzxingcheng', 'ytkd', 'gatien', 'gaticn', 'jcex', 'peex', 'kxda', 'advancing', 'huiwen', 'yxexpress', 'donghong', 'feiyuanvipshop', 'hlyex', 'kuaiyouda', 'riyuwuliu', 'sutongwuliu', 'nanjingshengbang', 'anposten', 'japanposten', 'postdanmarken', 'brazilposten', 'postnlcn', 'postnl', 'emsukrainecn', 'emsukraine', 'ukrpostcn', 'ukrpost', 'haihongmmb', 'fedexuk', 'fedexukcn', 'dingdong', 'upsfreight', 'abf', 'purolator', 'bpost', 'bpostinter', 'lasership', 'yodel', 'dhlnetherlands', 'myhermes', 'fastway', 'chronopostfra', 'selektvracht', 'lanhukuaidi', 'belgiumpost', 'upsmailinno', 'postennorge', 'swisspostcn', 'swisspost', 'royalmailcn', 'dhlbenelux', 'novaposhta', 'dhlpoland', 'estes', 'tntuk', 'deltec', 'opek', 'italysad', 'mrw', 'chronopostport', 'correosdees', 'directlink', 'eltahell', 'ceskaposta', 'siodemka', 'seur', 'jiuyicn', 'hrvatska', 'bulgarian', 'portugalseur', 'ecfirstclass', 'dtdcindia', 'safexpress', 'koreapost', 'tntau', 'thailand', 'skynetmalaysia', 'malaysiapost', 'malaysiaems', 'saudipost', 'southafrican', 'ocaargen', 'nigerianpost', 'chile', 'israelpost', 'estafeta', 'gdkd', 'mexico', 'romanian', 'tntitaly', 'multipack', 'portugalctt', 'interlink', 'hzpl', 'gatikwe', 'redexpress', 'mexicodenda', 'tcixps', 'hre', 'speedpost', 'emsinten', 'asendiausa', 'chronopostfren', 'italiane', 'gda', 'chukou1', 'huangmajia', 'anlexpress', 'shipgce', 'xlobo', 'emirates', 'nsf', 'pakistan', 'shiyunkuaidi', 'ucs', 'afghan', 'belpost', 'quantwl', 'efs', 'tntpostcn', 'gml', 'gtongsudi', 'donghanwl', 'rpx', 'rrs', 'htongexpress', 'kyrgyzpost', 'latvia', 'libanpost', 'lithuania', 'maldives', 'malta', 'macedonia', 'newzealand', 'moldova', 'bangladesh', 'serbia', 'cypruspost', 'tunisia', 'uzbekistan', 'caledonia', 'republic', 'haypost', 'yemen', 'india', 'england', 'jordan', 'vietnam', 'montenegro', 'correos', 'xilaikd', 'greenland', 'phlpost', 'ecuador', 'iceland', 'emonitoring', 'albania', 'aruba', 'egypt', 'omniva', 'leopard', 'sinoairinex', 'hyk', 'ckeex', 'hungary', 'macao', 'postserv', 'kuaitao', 'peru', 'indonesia', 'kazpost', 'lbbk', 'bqcwl', 'pfcexpress', 'csuivi', 'austria', 'ukraine', 'uganda', 'azerbaijan', 'finland', 'slovak', 'ethiopia', 'luxembourg', 'mauritius', 'brunei', 'quantium', 'tanzania', 'oman', 'gibraltar', 'byht', 'vnpost', 'anxl', 'dfpost', 'huoban', 'tianzong', 'bohei', 'bolivia', 'cambodia', 'bahrain', 'namibia', 'rwanda', 'lesotho', 'kenya', 'cameroon', 'belize', 'paraguay', 'sfift', 'hnfy', 'iparcel', 'bjxsrd', 'mailikuaidi', 'rfsd', 'letseml', 'cnpex', 'xsrd', 'chinatzx', 'qbexpress', 'idada', 'skynet', 'nedahm', 'czwlyn', 'wanboex', 'nntengda', 'sujievip', 'gotoubi', 'ecmsglobal', 'fastgo', 'ecmscn', 'eshunda', 'suteng', 'gdxp', 'yundaexus', 'szdpex', 'baishiwuliu', 'postnlpacle', 'ltexp', 'ztong', 'xtb', 'airpak', 'postnlchina', 'colissimo', 'pcaexpress', 'hanrun', 'cosco', 'sundarexpress', 'ajexpress', 'arkexpress', 'adaexpress', 'changjiang', 'bdatong', 'stoexpress', 'epanex', 'shunjiefengda', 'nmhuahe', 'deutschepost', 'baitengwuliu', 'pjbest', 'quansutong', 'zhongjiwuliu', 'jiuyescm', 'tykd', 'dabei', 'chengji', 'chengguangkuaidi', 'sagawa', 'lantiankuaidi', 'yongchangwuliu', 'birdex', 'yizhengdasuyun', 'sdyoupei', 'trakpak', 'gts', 'aolau', 'yiex', 'tongdaxing', 'hkposten', 'flysman', 'zhuanyunsifang', 'ilogen', 'dongjun', 'japanpost', 'jiajiatong56', 'jrypex', 'xaetc', 'doortodoor', 'xintianjie', 'sd138', 'hjs', 'quanxintong', 'amusorder', 'junfengguoji', 'kingfreight', 'subida', 'sucmj', 'yamaxunwuliu', 'jinchengwuliu', 'jgwl', 'yufeng', 'zhichengtongda', 'rytsd', 'hangyu', 'pzhjst', 'yousutongda', 'qinyuan', 'auexpress', 'zhdwl', 'fbkd', 'huada', 'fox', 'huanqiu', 'huilian', 'a2u', 'ueq', 'scic', 'yidatong', 'ruexp', 'htwd', 'speedoex', 'lianyun', 'jieanda', 'shlexp', 'ewe', 'abcglobal', 'mangguo', 'goldhaitao', 'jiguang', 'ftd', 'dcs', 'chengda', 'zhonghuan', 'shunbang', 'qichen', 'auex', 'aosu', 'aus', 'tianma', 'mjexp', 'chunfai', 'zenzen', 'mxe56', 'hipito', 'pengcheng', 'guanting', 'jinan', 'haidaibao', 'cllexpress', 'banma', 'youjia', 'buytong', 'xingyuankuaidi', 'quansu', 'sunjex', 'lutong', 'xynyc', 'xiaocex', 'airgtc', 'dindon', 'hqtd', 'haoyoukuai', 'yongwangda', 'mchy', 'flyway', 'qzx56', 'bsht', 'ilyang', 'xianfeng', 'timedg', 'meiquick', 'tny', 'valueway', 'sunspeedy', 'bphchina', 'yingchao', 'correoargentino', 'vanuatu', 'barbados', 'samoa', 'fiji', 'edlogistics', 'esinotrans', 'kuachangwuliu', 'cnausu', 'gslhkd', 'ccd', 'benteng', 'mapleexpress', 'topspeedex', 'yjxlm', 'otobv', 'jmjss', 'onehcang', 'hfwuxi', 'wtdchina', 'shunjieda', 'qskdyxgs', 'tlky', 'cloudexpress', 'speeda', 'zhongtongguoji', 'xipost', 'nle', 'nlebv', 'stkd', 'sinatone', 'auod', 'ahdf', 'wzhaunyun', 'lntjs', 'iexpress', 'bcwelt', 'euasia', 'ycgky', 'ledii', 'gswtkd', 'zyzoom', 'globaltracktrace', 'sendtochina', 'exfresh', 'emsluqu', 'sccod', 'runhengfeng', 'hkems', 'skypost', 'ruidaex', 'decnlh', 'suning', 'nzzto', 'lmfex', 'lineone', 'sihaiet', 'wotu', 'dekuncn', 'zsky123', 'hongjie', 'hongxun', 'ahkbps'];

        if (!in_array($code, $allow_shipping_code)) {
            throw new InvalidArgumentException('Current ShippingCode is not support');
        }

        if ($code == 'shunfeng' && empty($phone)) {
            throw new InvalidArgumentException('This Order Need PhoneNumber');
        }

        $param = [
            'company' => $code,
            'key' => $this->app_key,
            'number' => $number,
            'parameters' => [
                'callbackurl' => ApiTrackingConfig::callBackUrl($companyId,ApiTrackingConfig::TYPE_KUAIDI_100),
                'phone' => $phone
            ]
        ];
        $param = json_encode($param);

        $post = [
            'param' => $param,
            'sign' => strtoupper(md5($param . $this->app_key . $this->app_id))
        ];

        try {
            $response = $this->getHttpClient()->request('POST', 'https://poll.kuaidi100.com/poll', [
                'form_params' => $post
            ])->getBody()->getContents();
        } catch (GuzzleException $e) {
            throw new HttpException($e->getMessage(), $e->getCode(), $e);
        }

        return $response;
    }

    /**
     * @return Client
     */
    public function getHttpClient()
    {
        return new Client($this->guzzleOptions);
    }

    /**
     * @return Client
     */
    public function getGuzzleOptions()
    {
        return new Client($this->guzzleOptions);
    }

    /**
     * @param $options
     */
    public function setGuzzleOptions($options)
    {
        $this->guzzleOptions = $options;
    }
}


