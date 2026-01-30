import { html, customPinyin } from "pinyin-pro";

// 确保在 window 对象上定义 pinyinPro
window.pinyinPro = {
    html,
    customPinyin,
};

window.pinyinPro.customPinyin({
    将进酒: "qiāng jìn jiǔ",
    夕阳斜: "xī yáng xiá",
    万竿斜: "wàn gān xiá",
    春风拂槛: "chūn fēng fú jiàn",
    槛外: "jiàn wài",
    鬓毛衰: "bìn máo cuī",
    朝如青丝: "zhāo rú qīng sī",
    天姥: "tiān mǔ",
    泪不乾: "lèi bù gān",
    重叠: "chóng dié",
    曲项: "qū xiàng",
    长相: "cháng xiāng",
});

// 导出以确保代码被执行
export default window.pinyinPro;
