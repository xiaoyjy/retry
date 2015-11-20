

/**
 * ���ܣ� ��ʾ��ʾ����
 * ���ڣ� 2007-06-27
 * �汾�� 1.0
 */
var tipIframeNumber = 0;
var selectsVisibilityStatus = null;
var selectsDisabledStatus = null;
var sn_pop_PromptValue = null;// ����������

function alert(info, title) {
	console.log(info + title);
    //var a = new Alert(info.replace("\n", "<br>"), title);
    //a.make();
}

function showAlert(info, title) {
    var a = new Alert(info, title);
    a.make();
}

function confirm(info, title, _yesFunction, _noFunction) {
    var a = new Confirm(info.replace("\n", "<br>"), title, _yesFunction, _noFunction);
    a.make();
}
function showConfirm(info, title, _yesFunction, _noFunction) {
    var a = new Confirm(info, title, _yesFunction, _noFunction);
    a.make();
}

function prompt(info, title, callbackFunction) {
    var a = new Prompt(info.replace("\n", "<br>"), title, callbackFunction);
    a.make();
}
function showPrompt(info, title, callbackFunction) {
    var a = new Prompt(info, title, callbackFunction);
    a.make();
}


/**
 * ��Ϣ��ʾ����
 * 
 * ������ info:��ʾ����; title: ��ʾ����
 */
function Alert(info, title) {

    this.info  = (info!=null && info.length>0) ? info : "δ�ṩ��ʾ��Ϣ"; // ��ʾ����
    this.title = (title!=null && title.length>0) ? title : "��ʾ";// ��ʾ����
    this.iframeId = 'AlertFrame' + Math.ceil(Math.random() * 1000000);

    /**
     * ������ ��ʼ����ʾ��
     * ������ obj;��ʾ��; iframeId:IFrame����;  info:��ʾ����; title: ��ʾ����
     * ���أ� ��
     */
    this.initAlertBody = function(obj){
        with(obj.style) {
            position="absolute";
            width="400";
            height="150";
            backgroundColor="#ffffff";
        }
        obj.style.left=window.document.body.clientWidth/2-200;
        //obj.style.top=window.document.body.clientHeight/3;
        obj.style.top=screen.availHeight/3 - 100;

        var htmlCode = "<table border=0 cellpadding=0 cellspacing=1 bgcolor=#000000 width=100% height=100%>";
        htmlCode += "<tr height=30>";
        htmlCode += "<td align=left style='font-size: 12px;text-align: center;color: #FFFFFF;text-decoration: none;background-color:#bcd2ef;line-height: 22px;'>&nbsp;&nbsp;";
        htmlCode += this.title +"</td>";
        htmlCode += "</tr>";
        htmlCode += "<tr>";
        htmlCode += "<td align='center' bgcolor='#FFFFFF' style='font-size:12px;color:#000000;vertical-align: middle;'>";
        htmlCode += this.info +"</td>";
        htmlCode += "</tr>";
        htmlCode += "<tr height=30 bgcolor='#FFFFFF'>";
        htmlCode += "<td align=center>";
        htmlCode += "<input type='button' id='btnOk' name='btnOk' value='ȷ ��' onclick='parent.closeWin(\""+this.iframeId+"\")' style='BORDER-BOTTOM: #33a6dc 1px solid; TEXT-ALIGN: center; BORDER-LEFT: #33a6dc 1px solid; PADDING-BOTTOM: 0px; LINE-HEIGHT: 20px; MARGIN: 1px 0px 1px 4px; PADDING-LEFT: 7px; PADDING-RIGHT: 7px; BACKGROUND: url(img/button_bg.jpg) repeat-x center center; HEIGHT: 22px; COLOR: #025c90; FONT-SIZE: 12px; BORDER-TOP: #33a6dc 1px solid; CURSOR: hand; BORDER-RIGHT: #33a6dc 1px solid; PADDING-TOP: 0px'>";
        htmlCode += "</td>";
        htmlCode += "</tr>";
        htmlCode += "</table>";
        obj.innerHTML = htmlCode;
        
        var ifrm = obj.parentElement.children[0];
        obj.innerHTML = htmlCode;
        ifrm.style.left = obj.style.left;
        ifrm.style.top = obj.style.top;
        ifrm.style.width = obj.style.width;
        ifrm.style.height = obj.style.height;
        
        obj.parentElement.parentElement.document.getElementById("btnOk").focus();
    }

    this.make = function() {
        var pBody = initPopWindow(this.iframeId);
        this.initAlertBody(pBody);
    }
}


function Confirm(info, title, _yesFunction, _noFunction){

    this.info  = (info!=null && info.length>0) ? info : "δ�ṩ��ʾ��Ϣ"; // ��ʾ����
    this.title = (title!=null && title.length>0) ? title : "ȷ��";// ��ʾ����
    this.yesFunction = _yesFunction;
    this.noFunction  = _noFunction;
    this.iframeId = 'ConfirmFrame' + Math.ceil(Math.random() * 1000000);

    /**
     * ������ ��ʼ����ʾ�� conFirm��ʾ��
     * ������ obj;��ʾ��; info:��ʾ����; _yesFunction:click ok to trig; _noFunction:click no to trig
     * ���أ� ��
     */
    this.initConfirmBody = function(obj, _yesFunction, _noFunction){
        with(obj.style){
            position="absolute";
            width="400";
            height="150";
            backgroundColor="#ffffff";
        }
        obj.style.left=window.document.body.clientWidth/2-200;
        //obj.style.top=window.document.body.clientHeight/3;
        obj.style.top=screen.availHeight/3 - 100;

        var htmlCode = "<table border=0 cellpadding=0 cellspacing=1 bgcolor=#000000 width=100% height=100%><tr height=30>";
        htmlCode += "<td align=left style='font-size: 12px;text-align: center;color: #FFFFFF;text-decoration: none;background-color:#bcd2ef;line-height: 22px;' bgcolor=#9999ff>&nbsp;&nbsp;"+ this.title +"</td></tr>";
        htmlCode += "<tr><td align=center bgcolor=#FFFFFF style='font-size:12px;color:#000000;vertical-align: middle;'>";
        htmlCode += this.info + "</td></tr><tr height=30 bgcolor=#FFFFFF><td align=center>";


        htmlCode += "<input type='button' id='btnOk' name='btnOk' value=' �� ' onclick='";
        if(_yesFunction!=null){
            htmlCode += "parent."+_yesFunction+"();";
        }
        htmlCode += "parent.closeWin(\""+this.iframeId+"\");' style='BORDER-BOTTOM: #33a6dc 1px solid; TEXT-ALIGN: center; BORDER-LEFT: #33a6dc 1px solid; PADDING-BOTTOM: 0px; LINE-HEIGHT: 20px; MARGIN: 1px 0px 1px 4px; PADDING-LEFT: 7px; PADDING-RIGHT: 7px; BACKGROUND: url(img/button_bg.jpg) repeat-x center center; HEIGHT: 22px; COLOR: #025c90; FONT-SIZE: 12px; BORDER-TOP: #33a6dc 1px solid; CURSOR: hand; BORDER-RIGHT: #33a6dc 1px solid; PADDING-TOP: 0px'>";

        htmlCode += "&nbsp;&nbsp;&nbsp;";

        htmlCode += "<input type='button' value=' �� ' onclick='";
        if(_noFunction!=null){
            htmlCode += "parent."+_noFunction+"();";
        }
        htmlCode += "parent.closeWin(\""+this.iframeId+"\");' style='BORDER-BOTTOM: #33a6dc 1px solid; TEXT-ALIGN: center; BORDER-LEFT: #33a6dc 1px solid; PADDING-BOTTOM: 0px; LINE-HEIGHT: 20px; MARGIN: 1px 0px 1px 4px; PADDING-LEFT: 7px; PADDING-RIGHT: 7px; BACKGROUND: url(img/button_bg.jpg) repeat-x center center; HEIGHT: 22px; COLOR: #025c90; FONT-SIZE: 12px; BORDER-TOP: #33a6dc 1px solid; CURSOR: hand; BORDER-RIGHT: #33a6dc 1px solid; PADDING-TOP: 0px'>";

        htmlCode += "</td></tr></table>";
        obj.innerHTML = htmlCode;
        
        var ifrm = obj.parentElement.children[0];
        obj.innerHTML = htmlCode;
        ifrm.style.left = obj.style.left;
        ifrm.style.top = obj.style.top;
        ifrm.style.width = obj.style.width;
        ifrm.style.height = obj.style.height;
        
        obj.parentElement.parentElement.document.getElementById("btnOk").focus();
    }
    this.make = function() {
        var pBody = initPopWindow(this.iframeId);
        this.initConfirmBody(pBody, this.yesFunction, this.noFunction);
    }
}

function Prompt(info, title, callbackFunction) {

    this.info  = (info!=null && info.length>0) ? info : "δ�ṩ��ʾ��Ϣ"; // ��ʾ����
    this.title = (title!=null && title.length>0) ? title : "����";// ��ʾ����
    this.callbackFunction = callbackFunction;
    this.iframeId = 'PromptFrame' + Math.ceil(Math.random() * 1000000);
    
    /**
     * ������ ��ʼ�������
     * ������ obj;��ʾ��; iframeId:IFrame����; info:��ʾ����; callbackFunction:�����ȷ����ʱ�Ļص�����
     * ���أ� ��
     */
    this.initPromptBody = function initPromptBody(obj){
        with(obj.style) {
            position="absolute";
            width="400";
            height="150";
            backgroundColor="#ffffff";
        }
        obj.style.left=window.document.body.clientWidth/2-200;
        //obj.style.top=window.document.body.clientHeight/3;
        obj.style.top=screen.availHeight/3 - 100;

        var htmlCode = "<table border=0 cellpadding=0 cellspacing=1 bgcolor=#000000 width=100% height=100%><tr height=30>";
        htmlCode += "<td align=left style='font-size: 12px;text-align: center;color: #FFFFFF;text-decoration: none;background-image: url(/images/top_right.jpg);line-height: 22px;' bgcolor=#9999ff>&nbsp;&nbsp;";
        htmlCode += this.title +"</td></tr>";
        htmlCode += "<tr><td align=center bgcolor=#FFFFFF style='font-size:12px;color:#000000;vertical-align: middle;'>";
        htmlCode += "<span>"+this.info+"</span><input type='text' id='sn_pop_input' name='sn_pop_input' size=20></td></tr>";
        htmlCode += "<tr height=30 bgcolor=#FFFFFF><td align=center>";

        htmlCode += "<input type='button' id='btnOk' name='btnOk' value='ȷ��' onclick='parent.setPromptValue(this);";
        if(callbackFunction!=null){
            htmlCode += "if(parent."+callbackFunction+"()){parent.closeWin(\""+this.iframeId+"\");}";
        }else{
            htmlCode += "parent.closeWin(\""+this.iframeId+"\");";
        }
        htmlCode += "' style='height: 22px;width: 60px;text-align: center;border-right: #006699 1px solid;border-bottom: #006699 1px solid;border-top: #006699 1px solid;border-left: #006699 1px solid;padding-left: 0px;padding-bottom: 0px;padding-right: 0px;padding-top: 2px;cursor: hand;color: black;background-attachment: fixed;background-image: url(/images/report_button.jpg);background-repeat: repeat-x;background-position: center;'>";

        htmlCode += "&nbsp;&nbsp;&nbsp;";
        htmlCode += "<input type='button' value='ȡ��' onclick='parent.closeWin(\""+this.iframeId+"\");' style='height: 22px;width: 60px;text-align: center;border-right: #006699 1px solid;border-bottom: #006699 1px solid;border-top: #006699 1px solid;border-left: #006699 1px solid;padding-left: 0px;padding-bottom: 0px;padding-right: 0px;padding-top: 2px;cursor: hand;color: black;background-attachment: fixed;background-image: url(/images/report_button.jpg);background-repeat: repeat-x;background-position: center;'>";
        htmlCode += "</td></tr></table>";
        obj.innerHTML = htmlCode;
        
        var ifrm = obj.parentElement.children[0];
        obj.innerHTML = htmlCode;
        ifrm.style.left = obj.style.left;
        ifrm.style.top = obj.style.top;
        ifrm.style.width = obj.style.width;
        ifrm.style.height = obj.style.height;
        
        obj.parentElement.parentElement.document.getElementById("sn_pop_input").focus();
        obj.parentElement.parentElement.document.getElementById("sn_pop_input").select();
    }
    this.make = function(){
        sn_pop_PromptValue = null;
        var pBody = initPopWindow(this.iframeId);
        this.initPromptBody(pBody);
    }
}


function getPromptValue() {
    return sn_pop_PromptValue;
}

/**
 * ������ ��ʼ������Ϣ
 * ������ iframeId:IFrame����
 * ���أ� ���ݲ�
 */
function initPopWindow(iframeId){

    tipIframeNumber++;

    var ifm = document.createElement('iframe');
ifm.id = iframeId;
ifm.allowTransparency = 'true';
ifm.style.position="absolute";
    document.body.appendChild(ifm);

    ifm.style.width  = screen.availWidth;
    ifm.style.height = screen.availHeight;
    ifm.style.left = document.body.scrollLeft;
    ifm.style.top  = document.body.scrollTop;
    ifm.name = iframeId;

    var win = window.frames[ifm.name];
    win.document.write("<body leftmargin=0 topmargin=0 oncontextmenu='self.event.returnValue=false'><iframe border=0 frameBorder=0 style='position: absolute; filter=progid:DXImageTransform.Microsoft.Alpha(style=0,opacity=80);' ></iframe><div id=popbg></div><div id=popbody></div><div></div></body>");
    win.document.body.style.backgroundColor="transparent";

    document.body.style.overflow="hidden";

    var pBg   = win.document.body.children[1];
    var pBody = win.document.body.children[2];
    var pSnd  = win.document.body.children[3];

    hodeAllSelectStatus();
    initBg(pBg);
    initSnd(pSnd);

    return pBody;
}

/**
 * ������ ��ʼ��Ч
 * ������ obj;��Ч��
 * ���أ� ��
 */
function initSnd(obj){
    obj.innerHTML='<embed src="snd.mp3" loop="false" autostart="true" hidden=true></embed>';
}

/**
 * ������ ��ʼ��������
 * ������ obj;������
 * ���أ� ��
 */
function initBg(obj) {
    //if(tipIframeNumber<=1){
        with(obj.style){
            position="absolute";
            left="0";
            top="0";
            width="100%";
            height="100%";
            visibility="hidden";
            backgroundColor= "#E8F2FF";//"black"//"#aaaaaa";//
            filter="blendTrans(duration=0) alpha(opacity=60)";
        }

       // if (obj.filters.blendTrans.status != 2) {//no playing
        //    obj.filters.blendTrans.apply();
        //    obj.style.visibility="visible";
         //   obj.filters.blendTrans.play();
       // }
    //}
}

/**
 * ������ �������ֵ����һ������
 * ������ obj:��ʾ��; info:��ʾ����;
 * ���أ� ��
 */
function setPromptValue(obj) {
    var t = obj.parentElement.parentElement.parentElement.parentElement;
    sn_pop_PromptValue = t.rows[1].cells[0].children[1].value;
}

/**
 * ������ �ر�һ��
 * ������ ��
 * ���أ� ��
 */
function closeWin(iframeId) {

    var ifm = document.getElementById(iframeId);
    ifm.style.visibility="hidden";
    document.body.removeChild(ifm);

    tipIframeNumber--;
    if(tipIframeNumber==0) {
        restoreAllSelectStatus();
        document.body.style.overflow="auto";
    }
}

/**
 * ������ �������������б��״̬(�ɼ�״̬&����״̬)
 * ������ ��
 * ���أ� ��
 */
function hodeAllSelectStatus(){
    if(tipIframeNumber<=1){
        var obj = document.getElementsByTagName("SELECT");
        selectsVisibilityStatus = new Array();
        selectsDisabledStatus = new Array();
        for(var i=0;i<obj.length;i++) {
            selectsVisibilityStatus[i] = obj[i].style.visibility;
            selectsDisabledStatus[i] = obj[i].disabled;
            //obj[i].style.visibility="hidden";
            obj[i].disabled = true;
        }
    }
}

/**
 * ������ �ָ����������б��״̬(�ɼ�״̬&����״̬)
 * ������ ��
 * ���أ� ��
 */
function restoreAllSelectStatus() {
    var obj = document.getElementsByTagName("SELECT");
    for(var i=0; i<obj.length; i++) {
        obj[i].style.visibility = selectsVisibilityStatus[i];
        obj[i].disabled = selectsDisabledStatus[i];
    }
}

 function getBodyTop()   
  {   
  return   document.documentElement.scrollTop   ||   document.body.scrollTop   ||   0;   
  }
