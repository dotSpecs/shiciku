import { html, customPinyin } from "pinyin-pro";

// 确保在 window 对象上定义 pinyinPro
window.pinyinPro = {
    html,
    customPinyin,
};

// 导出以确保代码被执行
export default window.pinyinPro;
