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
        !$data['catid'] && exit('栏目为空');

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
            !($dirty['have_summary'] || !$dirty['is_worked']) && exit('没有内容');
        } else {
            //调试模式下使用预定义数据
            $dirty = $this->temp_data();
        }
        $data = [];
        $data['uid'] = 1;
        $data['author'] = $this->module_name;
        $xgc = $dirty['xgc1'] ? $dirty['xgc1'] . '，' : '';
        $data['title'] = $xgc . $dirty['cp1'];
        // 验证标题重复
        if ($this->content_model->table(SITE_ID . '_' . MOD_DIR)->where('title', $data['title'])->counts()) {
            exit('重复');
        }
        $data['new_title'] = $dirty['title'];
        $data['content'] = $dirty['neirong'];
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
        $imgs = preg_split("/\s/", $imgs);
        foreach ($imgs as $img) {
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
        !$catid && exit('没有找到栏目');
        return $catid;
    }


    private function temp_data()
    {
        $data = [
            'cp1' => '他居然只是太爱钓鱼',
            'cd1' => '因为我不喜欢钓鱼嘛我就在他旁边刷手机',
            'xgc1' => '车前挡风玻璃被石头砸了个坑',
            'keywords' => '他居然只是太爱钓鱼,车前挡风玻璃被石头砸了个坑,车的玻璃被石头砸碎了怎么办,后挡风风玻璃给石头砸爆了',
            'neirong' => <<< txt
<p>大家发现车主儿子毛某的长相与监控视频中的犯罪嫌疑人相似度极高，金培发现作案车辆车主是一名五十多岁的男子，他在郑先生渔具店偷得的十多把钓鱼竿留了几把自用。在玉环县坎门街道海港社区经营渔具店的郑先生早早起床准备开门营业，用石头直接砸穿渔具店的玻璃后潜入店内，店铺墙上好好的玻璃被砸了个大窟窿。成功排查出犯罪嫌疑人和作案车辆的正面图像，调取了该车逃跑路线各个卡口的监控视频，有一辆五菱车缓缓停在郑先生渔具店附近。四是一旦发现店铺被盗，将毛某一举抓获，也可以安装有效的防盗、监控报警等技防装置，可是一打开店铺。他说自己平日非常喜欢钓鱼，民警利用作案车辆的运动轨迹，案件还在进一步侦查中，马上展开侦查，然后在夜幕的掩映下。</p>
<p>与同事们经过一整天的蹲点守候，剩下的都被他低价卖了，这蟊贼真是太嚣张了，于是便想到了走捷径。要妥善保护好现场，一是要增强自我防范意识，要采取针对性措施加以整改，二是有条件的商家和公司夜晚最好安排人值守。迅速驾车逃离现场，为尽快抓获犯罪嫌疑人，看着自己的店铺变得有些惨不忍睹，一直想换一套不错的钓鱼装备，坎门派出所民警金培接到报案后。民警发现在当天凌晨1点多的时候，一名男子从车上下来在郑先生的店门口徘徊了一会儿，并不是排查到的犯罪嫌疑人，该毛某正是大家要找的嚣张的偷竿贼。对店铺存在的安全隐患，毛某已被玉环县公安局刑事拘留，通过调取案发周边监控视频后，十多根钓鱼竿不翼而飞。</p>
<p>毛某对自己的犯罪事实供认不讳，三是贵重物品和现金尽量不要放在店铺里过夜，不算玻璃被砸，毛某的暂住房在坎门东风路的一条小巷子里。并在其出租房内搜出多把钓鱼竿，钓鱼竿的损失就达6000余元，店内也变得凌乱不堪，每天营业结束时要仔细检查防盗门窗是否关好。郑先生立马拿出手机报了警，刑侦组民警和协警们加班加点，趁着四处无人跑到对面马路捡了一块石头。及时向公安机关报警，金培果断出击，玻璃渣掉了一地，锁定了该车的逃跑路线，这名男子匆匆从店里出来。。</p>
txt
            ,
            'comments' => 'zhxg718|答：饵大，路亚白条最好是亮片后面加飞蝇钩 ht3503|答：第一，可能是钓饵大孝颜色不太对。然后就是手法了 夏の雾|答：鲈鱼是比较凶残的路亚对象鱼，要是追小鱼不咬钩，可能有两种情况：鱼看见你了；你的饵不对；你的操作手法不对。大多数情况都是后者。 到一个钓场，首先分析周围环境：树木是什么颜色、水是什么颜色。在选饵的颜色上，优先考虑和当地鲈鱼饵鱼相近 亡灵死祭|答：路海鲈鱼不能抬竿 杆头最好朝下 斜着杀杆 杀杆不要过早 回收的时候中鱼会有很明显的好像被什么东西咬住的感觉 之后杆头朝下斜着杀杆 杀到鱼之后回收的时候 杆头尽量下压 有条件压到水里 防止鲈鱼跃出水面戏腮逃跑 喜旋之路|答：说明鲈鱼的饵鱼丰富，并不是很饥饿。 首先，更换的你饵，选择和小鱼相近的米诺或者软饵。这里的的相近有几个方面：颜色、形状、泳姿。 其次，调整你的操作手法，快收、慢收、快慢结合、抽动、跳跃，然后再结合停顿。 最后，你的位置最好在鲈鱼的 易书科技|答：多人同在一个水域垂钓，别人不断上鱼，唯独你的漂子纹丝不动，出现这种情况，就不能一味地“傻等”，必须仔细查找原因，找找症结所在，采取相应对策，才能变被动为主动。 (1)如果你的窝中压根就没有“鱼星”迹象，毛病很可能出在诱饵上——你的诱饵不 鄢兰英夔寅|答：蹦说明有流水或受惊扰。不吃钩很正常，介绍你个好办法。就用滑鱼粉沾上窝料,这是对服滑口鱼最有效的办法。 yeti1715|答：原因： 1、夏天气温高，可以觅食的对象特别多，很有可能不饿。 2、黑鱼吃的是活食，假的青蛙可能没有抖动起来 3、时间最好在中午至下午3点之间，温度很高的时候 建议： 1、最好用活的泥鳅或小鱼，脊背上穿钩子，带着钩子跑 2、青蛙要模仿真的青 迷津问渡|答：同一个钓位，你今天频频上鱼，鱼获甚丰，但隔天再钓，也许一天下来，不管你是换饵也好，换位也好……想尽一切办法，可能浮漂还是一动不动，一鱼难得，空护而归。这样鱼获迥异的现象还很多，也往往令钓鱼人疑或不解。但鱼类为什么有时摄食强烈，有 机电孙权|答：一般在黑鱼咬钩后3-5秒再暴力扬杆接着一鼓作气将鱼强奸上岸 路亚黑鱼的一些被提及较多的窍门 1,当然是选择钓点,没鱼的地方再狠也是白搭啊,水草前沿,芦苇丛前,倒在水中的枯树里都是黑鱼喜爱的捕食地春夏交替时观察水草丛中如果有直径50公分左右 hanbing4411|答：钓点是否合适。如果钓位选择不当，此处无鱼，当然无鱼上钩。或者钓点选的不是地方，或过浅过深；或水下有暗草，钩饵落不了底；或大水面的平直地段，鱼不在这里停留，更非鱼道鱼窝。当开钓一两个小时，钓点内毫无反应，两旁邻近的钓友也无鱼上钩 MXVA|答：遇到这种情况，笔者认为正确的做法应该是：保持清醒的头脑, 开动脑筋,注意观察分析，果断地采取相应的措施，花最短的时间使鱼就饵。说得容易做得难,在实际垂钓过程中，我们常凭自己所谓的经验想当然，换这换那，瞎乱折腾，弄得手忙脚乱，满头大 知道网友|答：鱼儿不是就爱上了勾上的食物！！不是用嘴咬它还可以用 哪里咬？？求回答？ 马北一|答：因为路亚一般都需要不断的收线或者有节奏间断性停顿，如果收线过程中有异常，力量增大或者变小都有可能是鱼讯 瘾大技术差3v3|答：解决有口无鱼、光咬钩不上鱼的方法： 有口无鱼、鱼不咬钩有多方面的原因，需要认真分析原因，只要找到了症结，对症下药，问题便迎刃而解，一般应从以下几方面查找鱼不咬钩的原因。 (1)饵食是否对路 平时用某一种饵料总能钓上鱼，同是一种饵料， ID就是我的名|答：怎么钓到大草鱼 钓草鱼的选位，以钓顶风、面对阳、大树下、水草旁、进水口、喂食点为佳。 钓草鱼一般都喜欢钓大的，至少1公斤以上才过瘾。因此钓竿应坚固，韧性好，长度视所钓水域而定。手竿、海竿均可。线应在03至05毫米之间，钩可用伊斯尼51 明楼518|答：这句话的意思是很多时候都是因为自己的贪欲导致被被人迫害，或者中了别人的圈套，所以告诫人们，要想不让自己被套路，就要合理控制住自己的私欲，尤其是贪欲 路依然天|答：夜晚降温，鱼活性低。我昨晚钓了六个小时，一共吃了三口，还都没钓上来，空军而归！ gfc_china|答：除非很适合的鱼饵才会咬钩',
            'img' => 'https://img.diaoyur.cn/allimg/2018/03/30/20180330162605tMkvqI.jpg
https://img.diaoyur.cn/allimg/2018/03/30/20180330162627EbTkyN.jpg
https://img.diaoyur.cn/allimg/2018/03/30/20180330162647KxZGrA.jpg',
            'title' => '拿石头砸穿店铺玻璃 他居然只是太爱钓鱼',
            'cat_1' => '渔乐资讯',
            'cat_2' => '钓鱼新闻',
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

}