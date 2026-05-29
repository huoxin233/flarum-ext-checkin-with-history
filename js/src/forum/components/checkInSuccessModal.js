import app from 'flarum/forum/app';
import Modal from 'flarum/common/components/Modal';

export default class CheckInSuccessModal extends Modal {
  className() {
    return 'checkInResultModal Modal--small';
  }

  title() {
    return (<div className="checkInResultModal successTitleText">{app.translator.trans('ziven-checkin.forum.check-in-success')}</div>);
  }

  content() {
    //
    const totalContinuousCheckIn = app.session.user.attribute("totalContinuousCheckIn");
    const forumCheckinSuccessPromptText = app.forum.attribute("forumCheckinSuccessPromptText");
    const forumCheckinSuccessPromptRewardText = app.forum.attribute("forumCheckinSuccessPromptRewardText");
    const forumCheckinRewardMoney = app.forum.attribute("forumCheckinRewardMoney");
    const moneyExtensionExist = app.forum.attribute('antoinefr-money.moneyname')!==undefined;

    let moneyName = "";
    let rewardText = "";
    let successTextClassName = "checkInResultModal hideText";
    let rewardTextClassName = "checkInResultModal hideText";

    if(forumCheckinSuccessPromptText!==""){
      successTextClassName = "checkInResultModal successText";
    }

    if(moneyExtensionExist===true && forumCheckinSuccessPromptRewardText!==""){
      moneyName = app.forum.attribute('antoinefr-money.moneyname') || '[money]';
      rewardText = moneyName.replace('[money]', forumCheckinRewardMoney);
      rewardTextClassName = "checkInResultModal rewardText";
    }

    return (
      <div className="Modal-body">
        <div className={successTextClassName}>{forumCheckinSuccessPromptText.replace('[days]', totalContinuousCheckIn)}</div>
        <div className={rewardTextClassName}>{forumCheckinSuccessPromptRewardText.replace('[reward]', rewardText)}</div>
      </div>
    );
  }
}
