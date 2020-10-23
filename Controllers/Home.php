<?php namespace Phpcmf\Controllers;

use Phpcmf\Library\Input;
use think\facade\Db;

class Home extends \Phpcmf\App
{
    /**
     * 请求对象
     * @var Input
     */
    protected $input;
    /**
     * 请求方法名
     * @var
     */
    protected $method;
    /**
     * 模块目录名
     * @var bool|string
     */
    protected $module_name;

    /**
     * 网站地址
     * @var
     */
    protected $base_url;
    protected $debug = 0; //0关闭  1开启

    public function __construct(... $params)
    {
        parent::__construct($params);
        $this->input = new Input();
        $this->method = $_SERVER['REQUEST_METHOD'];
        $this->module_name = $this->input->request('module');
        !$this->module_name && exit('模块名不能为空');
        $this->base_url = SITE_URL;

        if ($this->input->request('auth') != 'ala881020') {
            exit('全局变量错误');
        }
        //初始化模块
        $this->_module_init($this->module_name);

    }


    /**
     * url修改接口
     */
    public function url_update()
    {
        $table = \Phpcmf\Service::M()->dbprefix(SITE_ID . '_' . $this->module_name);
        $rows = Db::table($table)->select();
        if ($rows && !$rows->isEmpty()) {
            foreach ($rows as $row) {
                $url = $row['url'];
                $c_id = $row['id'];
                if (strpos($url, '?') !== false) {
                    $new_url = '';
                    $join = '-';
                    $c = '';
                    $id = '';
                    $p = '';
                    $query = preg_split('/\?/', $url, 2)[1];
                    $params = preg_split('/&/', $query);
                    foreach ($params as $param) {
                        list($k, $v) = explode('=', $param);
                        $k == 'c' && $c = $v;
                        $k == 'id' && $id = $v;
                        $k == 'page' && $p = $v;
                    }
                    $id && $new_url .= '/' . $c . $join . $id;
                    $new_url .= $p ? $join . $p : '';
                    $new_url .= '.html';
                    if ($new_url) {
                        Db::table($table)->where('id', $c_id)->data(['url' => $new_url])->update();
                    }
                }
            }
            return '成功';
        }
    }


    /**
     * 火车头文章发布接口
     */
    public function release()
    {
        if ($this->method == 'GET') {
            // 显示栏目
            foreach ($this->module['category'] as $t) {
                if ($t['child'] == 0 && $t['tid'] == 1) {
                    echo '<h1>' . $t['name'] . '<=>' . $t['id'] . '</h1>' . PHP_EOL;
                }
            }
            exit();
        } elseif ($this->method == 'POST') {
            // 获取入库数据
            $save = $this->get_save_data($_REQUEST);
            //入库 0代表新增
            $rt = $this->content_model->save(0, $save);

            $rt['code'] ? exit('成功') : exit('失败');
        }
    }


    /**
     * 获取最终入库的数据
     * @param array $params
     * @return array
     */
    protected function get_save_data(array $params)
    {
        $data = $this->get_clean_data($params);
        //获取主附表字段名
        $caches = $this->get_caches();
        //入库数据字典
        $save = [];
        // 将数据分表存储
        foreach ($caches as $i => $fields) {
            foreach ($fields as $field) {
                isset($data[$field]) && $save[$i][$field] = $data[$field];
            }
        }
        !$data['catid'] && exit('没有内容');

        $save[1]['uid'] = $save[0]['uid'] = $data['uid'];
        $save[1]['catid'] = $save[0]['catid'] = $data['catid'];
        $save[1]['url'] = '';
        // 说明来自审核页面
        define('IS_MODULE_VERIFY', 1);
        $save[1]['status'] = 9; //9表示正常发布，1表示审核里面
        $save[1]['hits'] = 0;   //点击量
        $save[1]['displayorder'] = 0;
        $save[1]['link_id'] = 0;
        $save[1]['inputtime'] = $save[1]['updatetime'] = SYS_TIME + rand(0, 7200);
        $save[1]['inputip'] = '127.0.0.1';

        return $save;
    }


    /**
     * 读取相关表的缓存数据
     * @return array
     */
    protected function get_caches()
    {
        $caches = [];
        // 主内容表缓存
        $caches[1] = $this->get_cache('table-' . SITE_ID, $this->content_model->dbprefix(SITE_ID . '_' . MOD_DIR));
        // 主栏目模型表缓存
        $cache = $this->get_cache('table-' . SITE_ID, $this->content_model->dbprefix(SITE_ID . '_' . MOD_DIR . '_category_data'));
        $cache && $caches[1] = array_merge($caches[1], $cache);
        //内容附表缓存
        $caches[0] = $this->get_cache('table-' . SITE_ID, $this->content_model->dbprefix(SITE_ID . '_' . MOD_DIR . '_data_0'));
        //栏目模型附表缓存
        $cache = $this->get_cache('table-' . SITE_ID, $this->content_model->dbprefix(SITE_ID . '_' . MOD_DIR . '_category_data_0'));
        $cache && $caches[0] = array_merge($caches[0], $cache);
        // 去重复
        $caches[0] = array_unique($caches[0]);
        $caches[1] = array_unique($caches[1]);
        return $caches;
    }

    /**
     * 处理输入数据
     * @param array $dirty
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    protected function get_clean_data(array $dirty)
    {
        if (!$this->debug) {
            (!$dirty['have_summary'] || !$dirty['is_worked']) && exit('没有内容');
        } else {
            //调试模式下使用预定义数据
            $dirty = $this->temp_data();
        }
        $data = [];
        $data['uid'] = 1;
        $data['author'] = $this->module_name;
        $xgc = $dirty['xgc1'] ? $dirty['xgc1'] . '，' : '';
        $data['title'] = trim($xgc . $dirty['cp1']);
        (!$data['title'] || !$dirty['neirong']) && exit('没有内容');
        // 验证标题重复
        if ($this->content_model->table(SITE_ID . '_' . MOD_DIR)->where('title', $data['title'])->counts()) {
            exit('重复');
        }

        $data['new_title'] = $dirty['title'];
        /*$data['content'] = $dirty['neirong'];*/
        $data['description'] = '';
        $data['keywords'] = $dirty['keywords'];
        //获取catid
        if (isset($dirty['catid']) && $dirty['catid']) {
            $data['catid'] = $dirty['catid'];
        } else {
            $data['catid'] = $this->get_catid($dirty);
        }
        //缩略图处理
        list($thumbId, $urls) = $this->get_thumbId_and_urls($dirty['img']);
        $data['thumb'] = $thumbId;
        //处理文章内容
        $new_paras = [];
        $paras = preg_split("/\n/", $dirty['neirong']);
        for ($i = 0; $i < count($paras); $i++) {
            $new_str = preg_replace("/\<.+?\>/", '', $paras[$i]);
            if ($this->utf8_strlen($new_str) < 10) {
                continue;
            }
            if (isset($urls[$i])) {
                $str = $new_str;
                $img = '<img src="' . $urls[$i] . '" alt="' . $data['title'] . '" title="' . $data['title'] . '">';
                $new_str = $this->rand_in_str($str, $img);
            }
            $new_paras[] = '<p>' . $new_str . '</p>';
        }
        shuffle($new_paras);
        $data['content'] = implode("\n", $new_paras);
        //处理评论内容
        if ($dirty['comments']) {
            $comments = [];
            $items = explode('|', $dirty['comments']);
            $items = array_slice($items, 1, -1);
            foreach ($items as $item) {
                $strs = preg_split("/\s/", $item);
                $one = array_pop($strs);
                $two = str_replace('答：', '', implode(' ', $strs));
                $comments[] = $one . "----" . $two;
            }
            $data['zdypl'] = implode("\n", $comments);
        }

        return $data;
    }

    /**
     * 获取缩略图ID和图片ID
     * @param string $imgs 图片路径
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    protected function get_thumbId_and_urls(string $imgs)
    {
        $thumbId = '';
        $urls = [];
        if (!$imgs) {
            return [$thumbId, $urls];
        }
        $imgs = preg_split("/\s+/", $imgs);
        foreach ($imgs as $img) {
            $img = trim($img);
            if (!$img) {
                continue;
            }
            $data = $this->get_uploadfile_info($img);
            if (!$data) {
                continue;
            }
            //取得第一张图片的id作为缩略图ID
            if (!$thumbId) {
                $table = \Phpcmf\Service::M()->dbprefix('attachment_data');
                $row = Db::table($table)->where('attachment', $data['file'])->find();
                $row && $thumbId = $row['id'];
            }
            if ($data['url']) {
                $urls[] = str_replace($this->base_url, '', $data['url']);
            }
        }
        return [$thumbId, $urls];
    }


    /**
     * 下载远程附件并入库,返回附件信息
     * @param string $url
     * @return bool|array
     */
    private function get_uploadfile_info(string $url)
    {
        // 下载远程文件
        $rt = \Phpcmf\Service::L('upload')->down_file([
            //url必须以扩展名结尾
            'url' => $url,
            // 0值不属于存储策略，填写策略ID号表示附件存储策略，可以是远程存储，可以是本地存储，如果不用存储策略就填0
            'attachment' => \Phpcmf\Service::M('attachment')->get_attach_info(0),
        ]);
        if ($rt['code']) {
            // $rt['data'] 附件入库后的信息数据
            // 附件归档
            $att = \Phpcmf\Service::M('attachment')->save_data($rt['data'], 'HCT_API');
            if ($att['code']) {
                // 归档成功
                return $rt['data'];
            }
        } else {
            return false;
        }
        return false;
    }

    /**
     * 获取栏目ID
     * @param array $data
     * @return int|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    protected function get_catid(array $data)
    {
        $table = \Phpcmf\Service::M()->dbprefix(dr_module_table_prefix('share') . '_category');
        $catid = 0;

        for ($i = 1; isset($data['cat_' . $i]) && $data['cat_' . $i]; $i++) {
            $res = Db::table($table)->where('name', $data['cat_' . $i])->find();
            $res && $catid = $res['id'];
        }
        !$catid && exit('失败');
        return $catid;
    }


    private function temp_data()
    {
        $data = [
            'cp1' => '世界十大富豪',
            'cd1' => '十大首富个个富可敌国语出惊人',
            'xgc1' => '中国女首富富可敌国',
            'keywords' => '世界十大富豪,中国女首富富可敌国,我真的可以富可敌国,中国富可敌国的人',
            'neirong' => <<< txt
<p>第一名：王健林(1700亿)公司：大连万达集团董事长经典语录：最好先定一个能达到的小目标，第二名：马云(1400亿)公司：阿里巴巴集团董事局主席、日本软银董事，比方说我先挣它一个亿。</p>
<p>行业分布：5位是搞互联网，1位基础建设，中国迪士尼靠你了，是因为你没有野心马总。</p>
<p>。</p>
txt
            ,
            'comments' => '挥洒的泪水gy|答：和珅所聚敛的财富，按《和珅违法全案档》录入的抄家清单，和珅家产已评价部分为2亿多两银(包含金银、人参、绸缎、玉器库、当铺、古玩铺、地步等)，而和家的房子花园、瑰宝古玩等则没有评价，不计在内。所有家产总共价值约值八亿两至十一亿两白银
怪咖少女97|答：人类的历史长河中的很多富豪，有些靠经商赚钱，有些靠贪污。钱财能给人带来一时的快活也能给人招来灾祸，能世世代代富有的人并不多。这十位，个个都可以说是富可敌国。但有的因为某些原因，大多没有好下常 富甲范蠡 辅助越王勾践灭吴的人就是他
大姨妈°rd4榱|答：[出自]：《汉书·邓通传》：“邓氏钱布天下，其富如此。”《九百岁水镇周庄》 《元史演义》里，沈万三被称为“财神爷”。《明史》记载：14世纪时，江南一个发了大财的巨商——沈万三，为大明的开国皇帝朱元璋造筑了南京城墙后，还溜须拍马地想为朝廷犒
9a3|答：《福布斯》雜志網絡版發布了2009年度全球富豪榜，微軟董事長比爾·蓋茨(Bill Gates)重新奪回全球首富頭銜。 以下爲《福布斯》雜志評出的2009年全球首富前10位排名： 1 微軟董事長比爾·蓋茨(Bill Gates)，淨資産400億美元 2 沃倫·巴菲特(Warren 
e130000|答：中国历史上最有钱的人 按财富绝对值指标：刘瑾、和珅、宋子文、伍秉鉴、邓通、梁冀、吕不韦、石崇、沈万三、陶朱公、 1、 刘瑾：明代正德朝大宦官，《亚洲华尔街日报》列为世界级富翁。其收受贿赂所得据说合为33万公斤黄金、805万公斤白银，而李
水都散人|答：1、刘瑾：明代正德朝大宦官，《亚洲华尔街日报》列为世界级富翁。其收受贿赂所得据说合为33万公斤黄金、805万公斤白银，而李自成打进北京时收缴崇祯一年的全国财政收入仅为白银20万公斤，此人不选，是无天理。 2、和珅：清代乾隆时大贪官，入讯
ycyy618|答：沈万三有钱！ 据说是富可敌国！ 如果把沈万三与当时的中国比的话，他是富可敌国！ 那么比尔盖茨现在的美国相比，他能敌国吗？ 不过话又说回来、此一时彼一时、应该是不可相提并论的！
巴巴拉小白兔|答：在过去一千年来全球最富有的50人中有6名是中国人。他们分别是：范蠡、沈万三、明朝太监刘瑾、清朝巨贪和绅、清商人伍秉鉴、民国宋子文。 1、刘瑾 明代太监刘瑾深得明武宗的宠信，官至司礼监掌印太监。曾权倾朝野，他欺上瞒下鱼肉百姓，受起贿来
白土知道|答：1、刘瑾：明代正德朝大宦官，《亚洲华尔街日报》列为世界级富翁。其收受贿赂所得据说合为33万公斤黄金、805万公斤白银，而李自成打进北京时收缴崇祯一年的全国财政收入仅为白银20万公斤。 2、和珅：清代乾隆时大贪官，入讯亚洲华尔街日报》世界
Bai龙城|答：结局各不相同像春秋的范蠡陶朱公，结果不错。明朝的沈万三就惨了被朱元璋整的家婆破人亡。这个一方面看商人本身会不会做人另外也是看皇帝人咋样了
脏兮兮的皱巴巴|答：这位世界首富叫伍秉鉴，他是福建人，去广东做生意，主要是中西贸易，他的私人资产达到两千六百万银元，豪宅可与大观园相比。 我们知道从古至今世界前十的富豪榜中没有什么中国人出现，在历史上出现过一位中国人，他曾是世界首富，他叫伍秉鉴，又
shangui2006|答：和珅啊，他富可敌国呀。
最后的思想|答：沈万三 沈万三,名富;字件荣,俗称万三。万三者,万户之中三秀,所以又称三秀,作为巨富的别号,元末明初人。 元朝中叶,沈万三的父亲沈*由吴兴(今浙江
xing3353|答：富可敌国是一个比喻词。不是说真的可以政府财政比较。我国一年军费约2000亿，加上官兵工资吃喝拉撒费，军属安置，退役补助等等一万亿估计也不够。纵观世界上那个富豪能拿出这个钱维持一年。美国的军费是世界第二到第十加一起的总和。
funvzhiyou5|答：首先是大家非常熟悉的最有名的大贪官和珅和珅，赫赫有名的人物，皇帝身边的大红人无人不知无人不晓。在当时的清朝明间流传和珅跌倒，嘉庆吃饱。据书中记载，那时候的清廷每年财政收入7000万两白银，和珅的家产清单明细一共有11亿两白银，在康乾
小田粽Hebe|答：把和珅的家产放到当今社会，也是可以进富豪榜前十的人物。 据史料证实，除去无法估价的古玩玉器、奇珍异宝外，能进行初步估值的绸缎、房契、地契价值约为2亿多两白银，墙里夹着黄金有710万余两。 嘉庆即位时，国库基本上已经空了，而和珅被抄出
知道网友|答：主要财团简介：  洛克菲勒财团  摩根财团  第一花旗银行财团  杜邦财团  波士顿财团  梅隆财团  克利夫兰财团  芝加哥财团  加利福尼亚财团  得克萨斯财团  三井
初级提问者|答：看这篇 中国这些省市富可敌国：看看你所在的省打败了哪些国家 街见闻以及网络 中国各个省份和城市的GDP，已经和世界上很多国家的GDP相当！ 复旦大学中国研究院院长张维为教授曾表示，中国是一个“百国之和”的文明型国家。 一方面，中国地方文化的
知道网友|答：樱兰高校 第3话：注意身体检查
知道网友|答：拥有武器的人在房间中，不需要听任何人的，只有他具有主动权',
            'img' => 'http://c.ckdzb.com/201609/2542.jpg
http://c.ckdzb.com/201609/2543.jpg',
            'title' => '中国十大富豪个个富可敌国！他一语震惊世界',
            'cat_1' => '世界之最',
            'cat_2' => '',
            'cat_3' => '',
            'have_summary' => '1',
            'is_worked' => '1',
        ];
        return $data;
    }


    /**
     * 向一个字符串随机插入一个字符串
     * @param string $oldstr 老字符串
     * @param string $instr 将要插入的字符串
     * @param string $encoding 字符串编码
     */
    function rand_in_str($oldstr, $instr, $encoding = 'utf-8')
    {
        $len = mb_strlen($oldstr, $encoding);
        $insert_point = mt_rand(1, $len - 1);
        $pre_str = mb_substr($oldstr, 0, $insert_point, $encoding);
        $after_str = mb_substr($oldstr, $insert_point, $len - $insert_point, $encoding);
        $newstr = $pre_str . $instr . $after_str;
        return $newstr;
    }


    /**
     * 统计字符数
     * @param string $str
     * @return int
     */
    function utf8_strlen(string $str)
    {
        if (!$str) {
            return 0;
        }
        $match = [];
        preg_match_all("/./us", $str, $match);
        return count($match[0]);
    }

}