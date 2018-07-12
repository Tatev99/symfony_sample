<?php
/**
 * Created by PhpStorm.
 * User: User
 * Date: 4/26/2018
 * Time: 4:52 PM
 */

namespace AppBundle\Controller;

use AppBundle\Model\ConstModel;
use AppBundle\Service\CodeManager;
use FOS\UserBundle\Model\UserInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/rest/")
 */
class RestController  extends Controller
{
    /**
     * @Route("get-token", name="rest_get_token")
     * @Method({"POST"})
     */
    public function restGenerateToken(Request $request){
        $translator = $this->get('translator');
        $em = $this->getDoctrine()->getManager();
        $userRepository = $this->getDoctrine()->getRepository('AppBundle:User');
        $parametersAsArray = [];
        if ($content = $request->getContent()) {
            $parametersAsArray = json_decode($content, true);
            if(empty($parametersAsArray))
                return new JsonResponse($translator->trans('Not Valid Json'));
        }else{
            return new JsonResponse($translator->trans('Something Wrong With Data'));
        }
        try{
            $username = $parametersAsArray['phoneNumber'];
            $password = $parametersAsArray['password'];
        }catch (\Exception $e){
            return new JsonResponse($translator->trans('Some Required Items Are Missing'));
        }
        $user=$userRepository->findOneBy(array('username'=>$username));
        if($user){
            $encoderService = $this->container->get('security.password_encoder');
            if($encoderService->isPasswordValid($user, $password)==false)
                $user=null;
        }
        if (!is_object($user) || !$user instanceof UserInterface || $user==null) {
            $response = array(
                'type' => 'error',
                'message' => $translator->trans('User With This Parameters Does not Exist !')
            );
            return new JsonResponse($response);
        }

        $user->setToken(uniqid(sha1($user->getId())));
        $user->setTokenDate(new \DateTime('+8hours'));
        $em->persist($user);
        $em->flush();
        $response = array(
            'type' => 'success',
            'token' => $user->getToken()
        );

        return new JsonResponse($response);

    }


    /**
     * @Route("pay-from-card-request", name="rest_pay_from_card")
     * @Method({"POST"})
     */
    public function restPayFromCardAction(Request $request){
        $translator = $this->get('translator');
        $funds = $this->get('app.service.funds');
        $em = $this->getDoctrine()->getManager();
        $userRepository = $this->getDoctrine()->getRepository('AppBundle:User');
        $parametersAsArray = [];
        if ($content = $request->getContent()) {
                $parametersAsArray = json_decode($content, true);
                if(empty($parametersAsArray))
                    return new JsonResponse($translator->trans('Not Valid Json'));
        }else{
            return new JsonResponse($translator->trans('Something Wrong With Data'));
        }

        ////////////////////////headers checking ///////////////////////////////////////////

        $isJson=false;
        $headers = apache_request_headers();
            if(isset($headers['Authorization'])){
                if (strpos($headers['Authorization'], 'key=') !== false) {
                    $token=str_replace('key=','',$headers['Authorization']);
                }
            }
            if(isset($headers['Content-Type'])){
                if($headers['Content-Type']=='application/json')
                $isJson=true;
            }

        if($isJson==false || !isset($token)){
            $response = array(
                'type' => 'error',
                'message' => $translator->trans('Something Wrong With Header Info')
            );
            return new JsonResponse($response);
        }

        ////////////////////////end headers checking ///////////////////////////////////////////

        try{
            $cardNumber = $parametersAsArray['card_id'];
            $paymentAmount = $parametersAsArray['amount'];
        }catch (\Exception $e){
            $response = array(
                'type' => 'error',
                'message' => $translator->trans('Some Required Items Are Missing')
            );
            return new JsonResponse($response);
        }

        $user=$userRepository->findOneBy(array('token'=>$token));
        if (!is_object($user) || !$user instanceof UserInterface || $user==null) {
            $response = array(
                'type' => 'error',
                'message' => $translator->trans('There Is No User Logged In With This Token')
            );
            return new JsonResponse($response);
        }

        if($user->getBranchUser()==null){
            $response = array(
                'type' => 'error',
                'message' => $translator->trans('This user does not have access to this section.')
            );
            return new JsonResponse($response);
        }


        if(strtotime(date('Y-m-d H:i:s')) > $user->getTokenDate()->getTimestamp()){
            $response = array(
                'type' => 'warning',
                'message' => $translator->trans('Token Date is Expired')
            );
            return new JsonResponse($response);
        }

        $response = array(
            'type' => 'success',
            'message' => $translator->trans('Գործարքն իրականացված է։')
        );


        if (!is_numeric($paymentAmount) || $paymentAmount<10) {
            $response = array(
                'type' => 'warning',
                'message' => $translator->trans('Գումարը հարկավոր է գրել թվերով։')
            );
            return new JsonResponse($response);
        }


        $customerRepository = $this->getDoctrine()->getRepository('AppBundle:Customer');


        $branch = $user->getBranchUser()->getBranch();
        if($branch==null){
            return new JsonResponse($translator->trans('This user does not have access to this section.'));
        }

        $customer = $customerRepository->findOneBy(array(
            'customerCardNumber' => $cardNumber
        ));

        if(!$customer){
            $response = array(
                'type' => 'warning',
                'message' => $translator->trans('Սխալ քարտի Համար')
            );
            return new JsonResponse($response);
        }

        if(!$customer->getUser()){
            $response = array(
                'type' => 'warning',
                'message' => $translator->trans('Այս քարտից հնարավոր չէ կանխիկացնել, խնդրում ենք գրանցվել այլ համակարգում։')
            );
            return new JsonResponse($response);
        }

        if($customer->getUser()->getAccount()){
            $customerBalance = $customer->getUser()->getAccount()->getBalance()->first();
        }
        else{
            $response = array(
                'type' => 'warning',
                'message' => $translator->trans('This User Does Not Have Account Yet')
            );
            return new JsonResponse($response);
        }

        if ($customerBalance->getFinalAmount() >= $paymentAmount) {
            $em = $this->getDoctrine()->getManager();
            $confirmation = new CodeManager($em);
            $sendSmsForConfirmation = $confirmation ->createConfirmationCode($user,ConstModel::Confirmation_ENRY_Payment,true,$paymentAmount,$customer);
            if($sendSmsForConfirmation!="" || $sendSmsForConfirmation!=null){
                $response = array(
                    'type' => 'success',
                    'message' => $translator->trans('Գործարքը հաստատելու համար ').$customer->getUser()->getUsername(). $translator->trans(' -ին ուղարկվել է հաղորդակցություն')
                );
            }else{
                $response = array(
                    'type' => 'warning',
                    'message' => $translator->trans('Հեռախոսահամարին չհաջողվեց ուղարկել հաղորդակցություն')
                );
            }

        } else {
            $response = array(
                'type' => 'danger',
                'message' => $translator->trans('ՈՒՇԱԴՐՈՒԹՅՈՒՆ։ Քարտում չկա բավարար միավոր։')
            );
        }

        return new JsonResponse($response);

    }



    /**
     * @Route("pay-from-card-submit", name="rest_pay_from_card_submit")
     * @Method({"POST"})
     */
    public function restPayFromCardSubmitAction(Request $request){
        $translator = $this->get('translator');
        $funds = $this->get('app.service.funds');
        $em = $this->getDoctrine()->getManager();
        $userRepository = $this->getDoctrine()->getRepository('AppBundle:User');
        $parametersAsArray = [];
        if ($content = $request->getContent()) {
            $parametersAsArray = json_decode($content, true);
            if(empty($parametersAsArray))
                return new JsonResponse($translator->trans('Not Valid Json'));
        }else{
            return new JsonResponse($translator->trans('Something Wrong With Data'));
        }

        ////////////////////////headers checking ///////////////////////////////////////////

        $isJson=false;
        $headers = apache_request_headers();
        if(isset($headers['Authorization'])){
            if (strpos($headers['Authorization'], 'key=') !== false) {
                $token=str_replace('key=','',$headers['Authorization']);
            }
        }
        if(isset($headers['Content-Type'])){
            if($headers['Content-Type']=='application/json')
                $isJson=true;
        }

        if($isJson==false || !isset($token)){
            $response = array(
                'type' => 'error',
                'message' => $translator->trans('Something Wrong With Header Info')
            );
            return new JsonResponse($response);
        }

        ////////////////////////end headers checking ///////////////////////////////////////////

        try{
            $code = $parametersAsArray['code'];
        }catch (\Exception $e){
            $response = array(
                'type' => 'error',
                'message' => $translator->trans('Code Is Missing')
            );
            return new JsonResponse($response);
        }

        $user=$userRepository->findOneBy(array('token'=>$token));
        if (!is_object($user) || !$user instanceof UserInterface || $user==null) {
            $response = array(
                'type' => 'error',
                'message' => $translator->trans('There Is No User Logged In With This Token')
            );
            return new JsonResponse($response);
        }

        if($user->getBranchUser()==null){
            $response = array(
                'type' => 'error',
                'message' => $translator->trans('This user does not have access to this section.')
            );
            return new JsonResponse($response);
        }


        if(strtotime(date('Y-m-d H:i:s')) > $user->getTokenDate()->getTimestamp()){
            $response = array(
                'type' => 'warning',
                'message' => $translator->trans('Token Date is Expired')
            );
            return new JsonResponse($response);
        }

        $response = array(
            'type' => 'success',
            'message' => $translator->trans('Գործարքն իրականացված է։')
        );

        if($code == null || !is_numeric($code) || $code>10000 || $code<1000){
            $response = array(
                'type' => 'danger',
                'message' => $translator->trans('Սխալ Կոդ')
            );
            return new JsonResponse($response);
        }


        $branch = $user->getBranchUser()->getBranch();
        if($branch==null){
            return new JsonResponse($translator->trans('This user does not have access to this section.'));
        }
        $branchAccount = null;
        $branchSaleAccount = null;

        foreach($branch->getAccount() as $account){

            if($account->getAccountType()->getId() == 1){ $branchAccount = $account; } //TODO poxel type-i ID
            if($account->getAccountType()->getId() == 4){ $branchSaleAccount = $account; }

        }



        $confirmation = new CodeManager($em);
        $customerCheck = $confirmation->checkCustomer($user,$code,ConstModel::Confirmation_ENRY_Payment);
        if (!is_object($customerCheck)) {
            $response = array(
                'type' => 'warning',
                'message' => $translator->trans('Սխալ Կոդ')
            );
            return new JsonResponse($response);
        }
        $customer = $customerCheck->getCustomer();
        $paymentAmount = $customerCheck->getAmount();


        if(!$customer){
            $response = array(
                'type' => 'warning',
                'message' => $translator->trans('Սխալ քարտի Համար')
            );
            return new JsonResponse($response);
        }

        if(!$customer->getUser()){
            $response = array(
                'type' => 'warning',
                'message' => $translator->trans('Այս քարտից հնարավոր չէ կանխիկացնել, խնդրում ենք գրանցվել այլ համակարգում։')
            );
            return new JsonResponse($response);
        }

        if($customer->getUser()->getAccount()){
            $customerBalance = $customer->getUser()->getAccount()->getBalance()->first();
        }
        else{
            $response = array(
                'type' => 'warning',
                'message' => $translator->trans('This User Does Not Have Account Yet')
            );
            return new JsonResponse($response);
        }

        if ($customerBalance->getFinalAmount() >= $paymentAmount) {
            $funds->fundsAdd(
                $em,
                $customer->getUser()->getAccount(),
                $branchAccount,
                $paymentAmount,
                ConstModel::TT_PaymentFromBonus,
                null
            );
            $notificationService = $this->get('app.service.notification');
            $notificationService->sendNotification([$customer->getUser()], ConstModel::Noticiation_For_Pay_From_Card);
        } else {
            $response = array(
                'type' => 'danger',
                'message' => $translator->trans('ՈՒՇԱԴՐՈՒԹՅՈՒՆ։ Քարտում չկա բավարար միավոր։')
            );
        }

        return new JsonResponse($response);

    }


    /**
     * @Route("services-list", name="rest_service_list")
     * @Method({"GET"})
     */
    public function restServiceListAction(){
        $serviceRep=$this->getDoctrine()->getRepository('AppBundle:Service');
        $services=$serviceRep->CreateQueryBuilder('s')
            ->select('s.id,s.serviceTitle')
            ->orderBy('s.serviceOrder','ASC')
            ->getQuery()
            ->getArrayResult();
        return new JsonResponse($services);
    }

    /**
     * @Route("entry", name="rest_entry")
     * @Method({"POST"})
     */

    public function restEntryAction(Request $request){

        $translator = $this->get('translator');
        $funds = $this->get('app.service.funds');
        $em = $this->getDoctrine()->getManager();

        $userRepository = $this->getDoctrine()->getRepository('AppBundle:User');
        $parametersAsArray = [];
        if ($content = $request->getContent()) {
            $parametersAsArray = json_decode($content, true);
            if(empty($parametersAsArray))
                return new JsonResponse($translator->trans('Not Valid Json'));
        }else{
            return new JsonResponse($translator->trans('Something Wrong With Data'));
        }

        $isJson=false;
        /////////////////////// headers checking ///////////////////////////////////
        $headers = apache_request_headers();
        if(isset($headers['Authorization'])){
            if (strpos($headers['Authorization'], 'key=') !== false) {
                $token=str_replace('key=','',$headers['Authorization']);
            }
        }
        if(isset($headers['Content-Type'])){
            if($headers['Content-Type']=='application/json')
                $isJson=true;
        }

        if($isJson==false || !isset($token)){
            $response = array(
                'type' => 'error',
                'message' => $translator->trans('Something Wrong With Header Info')
            );
            return new JsonResponse($response);
        }

        //////////////////////////// body checking ////////////////////////////////////////////

        try{
            $dataService = $this->getDoctrine()->getRepository('AppBundle:Service')->find($parametersAsArray['service_id']);
            $dataCardId = $parametersAsArray['card_id'];
            $dataAmount = $parametersAsArray['amount'];
        }catch (\Exception $e){
            $response = array(
                'type' => 'error',
                'message' => $translator->trans('Some Required Items Are Missing')
            );
            return new JsonResponse($response);
        }
        if($dataService==null){
            $response = array(
                'type' => 'error',
                'message' => $translator->trans('Not Valid Service Id')
            );
            return new JsonResponse($response);
        }

        ///////////////////////////////////////// user checking ////////////////////////////////////////////
        $user=$userRepository->findOneBy(array('token'=>$token));
        if (!is_object($user) || !$user instanceof UserInterface || $user==null ||  $user->getBranchUser()==null) {
            $response = array(
                'type' => 'error',
                'message' => $translator->trans('This user does not have access to this section.')
            );
            return new JsonResponse($response);
        }


        if(strtotime(date('Y-m-d H:i:s')) > $user->getTokenDate()->getTimestamp()){
            $response = array(
                'type' => 'warning',
                'message' => $translator->trans('Token Date is Expired')
            );
            return new JsonResponse($response);
        }


            $cardIdMatch = array();
            $amountMatch = array();
            // Validate Card Id
            preg_match('/^\d{5,6}$/', $dataCardId, $cardIdMatch);
            if(empty($cardIdMatch)){
                $response = array(
                    'type' => 'error',
                    'message' => $translator->trans('Քարտի ID-ն կարող է միայն պարունակել թվեր։')
                );
                return new JsonResponse($response);
            }
            // Validate Amount
            preg_match('/^\d{2,11}$/', $dataAmount, $amountMatch);
            if(empty($amountMatch)){
                $response = array(
                    'type' => 'error',
                    'message' =>  $translator->trans('Գումարը հարկավոր է գրել թվերով։')
                );
                return new JsonResponse($response);
            }

            // Add transaction
            if(!empty($cardIdMatch) && !empty($amountMatch)){
                $branch = $user->getBranchUser()->getBranch();
                $branchServices = $branch->getBranchService();
                $serviceTotalRate = 0;
                $serviceUserRate = 0;
                $customerRepository = $this->getDoctrine()->getRepository('AppBundle:Customer');
                $customer = $customerRepository->findOneByCustomerCardNumber($dataCardId);

                if($customer){
                    if($customer->getUser()){

                        $customerAccount = $customer->getUser()->getAccount();
                    } else {
                        $customerAccount = $customer->getAccount();
                    }
                    foreach($branchServices as $branchService){
                        if($branchService->getService() == $dataService){
                            $serviceTotalRate = $branchService->getServiceTotalRate();
                            $serviceUserRate = $branchService->getServiceUserRate();
                        }
                    }
                    if($serviceTotalRate !== 0 && $serviceUserRate !== 0){
                        $funds->fundsAdd(
                            $em,
                            $user->getAccount(),
                            $customerAccount,
                            $dataAmount,
                            ConstModel::TT_BranchSale,
                            $dataService
                        );
                        $response = array(
                            'type' => 'success',
                            'message' =>  $translator->trans("Գործարքն իրականացվել է հաջողությամբ")
                        );
                        $notificationService=$this->get('app.service.notification');
                        $notificationService->sendNotification([$customer->getUser()->getAccount()->getUser()],ConstModel::Noticiation_For_Entry);
                        return new JsonResponse($response);
                    } else {
                        $response = array(
                            'type' => 'error',
                            'message' => $translator->trans("Դուք չեք կարող օգտվել այս ծառայությունից")
                        );
                        return new JsonResponse($response);
                    }
                } else {
                    $response = array(
                        'type' => 'error',
                        'message' =>  $translator->trans("Սխալ քարտի համար")
                    );
                    return new JsonResponse($response);
                }
            }
        return new JsonResponse('Something Went Wrong');
    }



    /**
     * @Route("entry-documents", name="rest_entry_documents")
     * @Method({"POST"})
     */

    public function restEntryDocumentAction(Request $request){

        $translator = $this->get('translator');

        $userRepository = $this->getDoctrine()->getRepository('AppBundle:User');
        $parametersAsArray = [];
        if ($content = $request->getContent()) {
            $parametersAsArray = json_decode($content, true);
            if(empty($parametersAsArray))
                return new JsonResponse($translator->trans('Not Valid Json'));
        }else{
            return new JsonResponse($translator->trans('Something Wrong With Data'));
        }

        $isJson=false;
        /////////////////////// headers checking ///////////////////////////////////
        $headers = apache_request_headers();
        if(isset($headers['Authorization'])){
            if (strpos($headers['Authorization'], 'key=') !== false) {
                $token=str_replace('key=','',$headers['Authorization']);
            }
        }
        if(isset($headers['Content-Type'])){
            if($headers['Content-Type']=='application/json')
                $isJson=true;
        }

        if($isJson==false || !isset($token)){
            $response = array(
                'type' => 'error',
                'message' => $translator->trans('Something Wrong With Header Info')
            );
            return new JsonResponse($response);
        }
        ///////////////////////////////////////// user checking ////////////////////////////////////////////
        $user=$userRepository->findOneBy(array('token'=>$token));
        if (!is_object($user) || !$user instanceof UserInterface || $user==null ||  $user->getBranchUser()==null) {
            $response = array(
                'type' => 'error',
                'message' => $translator->trans('This user does not have access to this section.')
            );
            return new JsonResponse($response);
        }

        if(strtotime(date('Y-m-d H:i:s')) > $user->getTokenDate()->getTimestamp()){
            $response = array(
                'type' => 'warning',
                'message' => $translator->trans('Token Date is Expired')
            );
            return new JsonResponse($response);
        }

        $documentRepository = $this->getDoctrine()->getRepository('AppBundle:Document');
        $documentsSales = $documentRepository->createQueryBuilder('d')
            ->join('AppBundle:Account', 'a', 'WITH', 'd.accountTo = a')
            ->join('AppBundle:Branch', 'b', 'WITH', 'a.branch = b')
            ->join('AppBundle:BranchUser', 'bu', 'WITH', 'bu.branch = b')
            ->where('bu = :branchUser')
            ->andWhere('d.transactionType=:type')
            ->setParameter('branchUser',$user->getBranchUser())
            ->setParameter('type',ConstModel::TT_PaymentFromBonus)
            ->orderBy('d.documentDate','DESC')
            ->setMaxResults(ConstModel::Limit_For__Document)
            ->getQuery()
            ->getResult();

        $documentsAccumulates = $documentRepository->createQueryBuilder('d')
            ->join('AppBundle:Account', 'a', 'WITH', 'd.accountFrom = a')
            ->join('AppBundle:Account', 'acc', 'WITH', 'd.accountTo = acc')
//                ->join('AppBundle:Customer','cs','WITH','acc.customer = cs' )
            ->where('d.accountFrom=:account')
            ->andWhere('d.transactionType=:type')
            ->setParameter('account',$user->getAccount())
            ->setParameter('type',ConstModel::TT_BranchSale)
            ->orderBy('d.documentDate','DESC')
            ->setMaxResults(ConstModel::Limit_For__Document)
            ->getQuery()
            ->getResult();

        $documentsSalesArr = array();
        $index = 0;  //document.accountFrom.user.customer.customerCardNumber
        foreach ($documentsSales as $document){
            $documentsSalesArr[$index]['cardNumber'] = $document->getAccountFrom()->getUser()->getCustomer()->getCustomerCardNumber();
            $documentsSalesArr[$index]['service'] = ($document->getAccountTo()!=null)?$document->getAccountTo()->getBranch()->getBranchService()[0]->getService()->getServiceTitle():'-';
            $documentsSalesArr[$index]['amount'] = $document->getAmount();
            $documentsSalesArr[$index]['dateTime'] = $document->getDocumentDate()->format('Y-m-d H:i');
            $index++;
        }

        $documentsAccumulatesArr = array();
        $index = 0;
        foreach ($documentsAccumulates as $document){
            $documentsAccumulatesArr[$index]['cardNumber'] =($document->getAccountTo()->getUser()!=null)?$document->getAccountTo()->getUser()->getCustomer()->getCustomerCardNumber():$document->getAccountTo()->getCustomer()->getCustomerCardNumber();
            $documentsAccumulatesArr[$index]['service'] = $document->getService()->getServiceTitle();
            $documentsAccumulatesArr[$index]['amount'] = $document->getAmount();
            $documentsAccumulatesArr[$index]['dateTime'] = $document->getDocumentDate()->format('Y-m-d H:i');
            $index++;
        }
       $documentsResult= array(
           'sales'=>$documentsSalesArr,
           'accumulates'=>$documentsAccumulatesArr,
       );
        return new JsonResponse($documentsResult);
    }


    /**
     * @Route("logout", name="rest_logout")
     * @Method({"POST"})
     */
    public function restLogoutAction(Request $request){
        $translator = $this->get('translator');
        $em = $this->getDoctrine()->getManager();
        $userRepository = $this->getDoctrine()->getRepository('AppBundle:User');
        $parametersAsArray = [];

        $isJson=false;
        $headers = apache_request_headers();
        if(isset($headers['Authorization'])){
            if (strpos($headers['Authorization'], 'key=') !== false) {
                $token=str_replace('key=','',$headers['Authorization']);
            }
        }
        if(isset($headers['Content-Type'])){
            if($headers['Content-Type']=='application/json')
                $isJson=true;
        }

        if($isJson==false || !isset($token)){
            $response = array(
                'type' => 'error',
                'message' => $translator->trans('Something Wrong With Header Info')
            );
            return new JsonResponse($response);
        }

        if ($content = $request->getContent()) {
            $parametersAsArray = json_decode($content, true);
            if(empty($parametersAsArray))
                return new JsonResponse($translator->trans('Not Valid Json'));
        }else{
            return new JsonResponse($translator->trans('Something Wrong With Data'));
        }
        try{
            $username = $parametersAsArray['phoneNumber'];
        }catch (\Exception $e){
            return new JsonResponse($translator->trans('Some Required Items Are Missing'));
        }
        $user=$userRepository->findOneBy(
            array('username'=>$username,'token'=>$token)
        );

        if (!is_object($user) || !$user instanceof UserInterface || $user==null) {
            $response = array(
                'type' => 'error',
                'message' => $translator->trans('User With This Parameters Does not Exist!')
            );
            return new JsonResponse($response);
        }


        $user->setToken(null);
        $user->setTokenDate(null);
        $em->persist($user);
        $em->flush();
        $response = array(
            'type' => 'success',
            'message' => 'You Are Successfully Logout !'
        );

        return new JsonResponse($response);
    }

}