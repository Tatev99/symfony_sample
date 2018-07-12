<?php
/**
 * Created by PhpStorm.
 * User: Admin
 * Date: 6/15/2018
 * Time: 4:24 PM
 */

namespace AppBundle\Controller;


use AppBundle\Model\ConstModel;
use FOS\UserBundle\Model\UserInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/{_locale}")
 * requirements={
 *      "_locale": "am|en|ru"
 *  },
 *  defaults={
 *      "_locale": "am"
 *  }
 * )
 */
class HistoryController extends Controller
{

    /**
     * @Route("/history/find-branch-history-sales/{branchId}", name="findBranchHistorySales")
     */
    public function findSalesHistory(Request $request,$branchId,$_locale="am")
    {

        $documentRepository = $this->getDoctrine()->getManager()->getRepository('AppBundle:Document');

        $dateNow = date("Y-m-d");
        $docQuery = $documentRepository->createQueryBuilder('d')
            ->select(' b.branchTitle,d.documentDate,d.amount')
            ->join('AppBundle:Account', 'a', 'WITH', 'd.accountTo = a')
            ->join('AppBundle:Branch', 'b', 'WITH', 'a.branch = b')
            ->join('AppBundle:TransactionType', 't', 'WITH', 'd.transactionType = t')
            ->where('t.id = :transactionType')
            ->andWhere('b.id = :branchId')
            ->andWhere('d.documentDate >= :dateNow')
            ->setParameter('transactionType', 7)
            ->setParameter('branchId', $branchId)
            ->setParameter('dateNow', $dateNow)
            ->orderBy('d.documentDate','DESC')
            ->setMaxResults(ConstModel::Limit_For__Document)
            ->getQuery();

        $documents = $docQuery->getArrayResult();


        return $this->render('UserBundle:Black:history-sales-list.html.twig',array(
            'documents' => $documents,
            'branchId'  => $branchId
        ));
    }

    /**
     * @Route("/history/find-branch-history-accumulates/{branchId}", name="findBranchHistoryAccumulates")
     */
    public function findAccumulatesHistory(Request $request,$branchId,$_locale="am"){

        $dateNow = date("Y-m-d");
        $documentRepository = $this->getDoctrine()->getManager()->getRepository('AppBundle:Document');
        $docQuery = $documentRepository->createQueryBuilder('d')
            ->select('u.username,d.documentDate, d.bonusPoints')

            ->join('AppBundle:Account', 'a', 'WITH', 'd.accountFrom = a')
            ->join('AppBundle:User', 'u', 'WITH', 'a.user = u')
            ->join('AppBundle:BranchUser', 'bu', 'WITH', 'bu.user = u')
            ->join('AppBundle:Branch', 'b', 'WITH', 'bu.branch = b')
            ->join('AppBundle:TransactionType', 't', 'WITH', 'd.transactionType = t')

            ->where('b.id = :branchId')
            ->andWhere('t.id = :transactionType')
            ->andWhere('d.documentDate >= :dateNow')
            ->setParameter('branchId', $branchId)
            ->setParameter('transactionType', 9)
            ->setParameter('dateNow', $dateNow)

            ->orderBy('d.accountFrom', 'DESC')
            ->setMaxResults(ConstModel::Limit_For__Document)
            ->getQuery()
        ;

        $documents = $docQuery->getArrayResult();

        return $this->render('UserBundle:Black:history-accumulate-list.html.twig',array(
            'documents' => $documents,
            'branchId'  => $branchId
        ));
    }

    /**
     * @Route("/history/find-provider-history-filter", name="findProviderHistoryFilter")
     */
    public function getProviderHistory(Request $request,$_locale="am"){

        $translator = $this->get('translator');
        $user = $this->getUser();
        if (!is_object($user) || !$user instanceof UserInterface || $user->getProvider()==null) {
            throw new AccessDeniedException($translator->trans("Դուք չեք կարող մուք գործել այս բաժին։"));
        }
        $minDate   = ($request->get('filterdatemin') != "") ? $request->get('filterdatemin') : date('Y-m-d', strtotime(date('Y-m-d') . "-100 years"));
        $maxDate   = ($request->get('filterdatemax') != "") ? $request->get('filterdatemax') : date('Y-m-d', strtotime(date('Y-m-d') . "+1 day"));

        $em = $this->getdoctrine()->getManager();
        $connection = $em->getConnection();

        $statement = $connection->prepare("
        
            SELECT 
                d.`document_date` AS `DocumentDate` ,
                d.`amount` AS `DocumentAmount` ,
                t.`transaction_amount` AS `TransactionAmount` ,
                cus.`customer_card_number` AS `CardNumber` 
            
            FROM `document` d 
                JOIN `transaction` t   ON t.`document_id`   = d.`id`
                JOIN `account` 	   acc ON d.`account_to_id` = acc.`id`
                JOIN `user`        u   ON acc.`user_id` = u.`id`
                JOIN `customer`    cus ON cus.`user_id` = u.`id`
                
            WHERE t.`account_id` =:userId AND d.document_date >=:minDate AND d.document_date <=:maxDate
            
        ");

        $statement->bindValue('userId', $user->getAccount()->getId());
        $statement->bindValue('minDate', $minDate);
        $statement->bindValue('maxDate', $maxDate);
        $statement->execute();
        $documents = $statement->fetchAll();

        return new JsonResponse($documents);
    }

    /**
     * @Route("/history/find-branch-history-accumulates-filter", name="findBranchHistoryAccumulatesFilter")
     */
    public function findAccumulatesHistoryFilter(Request $request,$_locale="am")
    {
        $minDate   = ($request->get('filterdatemin') != "") ? $request->get('filterdatemin') : date('Y-m-d', strtotime(date('Y-m-d') . "-100 years"));
        $maxDate   = ($request->get('filterdatemax') != "") ? $request->get('filterdatemax') : date('Y-m-d', strtotime(date('Y-m-d') . "+1 day"));
        $branchId = $request->get('branch_id');


        $documentRepository = $this->getDoctrine()->getManager()->getRepository('AppBundle:Document');

        $docQuery = $documentRepository->createQueryBuilder('d')
            ->select('u.username,d.documentDate, d.bonusPoints')

            ->join('AppBundle:Account', 'a', 'WITH', 'd.accountFrom = a')
            ->join('AppBundle:User', 'u', 'WITH', 'a.user = u')
            ->join('AppBundle:BranchUser', 'bu', 'WITH', 'bu.user = u')
            ->join('AppBundle:Branch', 'b', 'WITH', 'bu.branch = b')
            ->join('AppBundle:TransactionType', 't', 'WITH', 'd.transactionType = t')

            ->where('b.id = :branchId')
            ->andWhere('t.id = :transactionType')
            ->andWhere('d.documentDate >=:minDate')
            ->andWhere('d.documentDate <=:maxDate')


            ->setParameter('branchId', $branchId)
            ->setParameter('transactionType', 9)
            ->setParameter('maxDate', $maxDate)
            ->setParameter('minDate', $minDate)

            ->orderBy('d.accountFrom', 'DESC')
            ->setMaxResults(ConstModel::Limit_For__Document)
            ->getQuery();

        $documents = $docQuery->getArrayResult();

        return new JsonResponse($documents);
    }

    /**
     * @Route("/history/find-branch-history-sales-filter", name="findBranchHistorySalesFilter")
     */
    public function findSalesHistoryFilter(Request $request,$_locale="am")
    {

        $minDate   = ($request->get('filterdatemin') != "") ? $request->get('filterdatemin') : date('Y-m-d', strtotime(date('Y-m-d') . "-100 years"));
        $maxDate   = ($request->get('filterdatemax') != "") ? $request->get('filterdatemax') : date('Y-m-d', strtotime(date('Y-m-d') . "+1 day"));
        $branchId = $request->get('branch_id');

        $documentRepository = $this->getDoctrine()->getManager()->getRepository('AppBundle:Document');

        $docQuery = $documentRepository->createQueryBuilder('d')
            ->select(' b.branchTitle,d.documentDate,d.amount')
            ->join('AppBundle:Account', 'a', 'WITH', 'd.accountTo = a')
            ->join('AppBundle:Branch', 'b', 'WITH', 'a.branch = b')
            ->join('AppBundle:TransactionType', 't', 'WITH', 'd.transactionType = t')


            ->where('t.id = :transactionType')
            ->andWhere('b.id = :branchId')
            ->andWhere('d.documentDate >=:minDate')
            ->andWhere('d.documentDate <=:maxDate')


            ->setParameter('transactionType', 7)
            ->setParameter('branchId', $branchId)
            ->setParameter('maxDate', $maxDate)
            ->setParameter('minDate', $minDate)

            ->orderBy('d.documentDate','DESC')
            ->setMaxResults(ConstModel::Limit_For__Document)
            ->getQuery();

        $documents = $docQuery->getArrayResult();

        return new JsonResponse($documents);
    }

    /**
     * @Route("/history/find-branch-history-with-dates", name="findBranchHistoryWithDates")
     */
    public function findBranchHistoryWithDates(Request $request,$_locale="am"){

        $customerId = $request->get('account_id');
        $type = $request->get('transactionTypeId');

        $minDate   = ($request->get('filterdatemin') != "") ? $request->get('filterdatemin') : date('Y-m-d', strtotime(date('Y-m-d') . "-100 years"));
        $maxDate   = ($request->get('filterdatemax') != "") ? $request->get('filterdatemax') : date('Y-m-d', strtotime(date('Y-m-d') . "+1 day"));

        $customerRepository = $this->getDoctrine()->getManager()->getRepository('AppBundle:Customer');
        $customer = $customerRepository->findBy(['id' => $customerId]);
        $documentRepository = $this->getDoctrine()->getManager()->getRepository('AppBundle:Document');

        if(!isset($customer[0])) {
            $docs = [];
        }
        else {
            if ($type == 7) {
                $docQuery = $documentRepository->createQueryBuilder('d')
                    ->select('d.amount, d.bonusPoints,d.documentDate')
                    ->join('AppBundle:Account', 'a', 'WITH', 'd.accountFrom = a')
                    ->join('AppBundle:User', 'u', 'WITH', 'a.user = u')
                    ->join('AppBundle:TransactionType', 't', 'WITH', 'd.transactionType = t')
                    ->where('u =:customer')
                    ->andWhere('t.id =:typeId')
                    ->andWhere('d.documentDate >=:minDate')
                    ->andWhere('d.documentDate <=:maxDate')
                    ->setParameter('customer', $customer[0])
                    ->setParameter('typeId', $type)
                    ->setParameter('minDate', $minDate)
                    ->setParameter('maxDate', $maxDate)
                    ->orderBy('d.documentDate', 'DESC')
                    ->setMaxResults(ConstModel::Limit_For__Document)
                    ->getQuery();


                $docs = $docQuery->getArrayResult();
                array_push($docs, "Type7");
            } else {
                $docQuery = $documentRepository->createQueryBuilder('d')
                    ->select('d.amount, d.bonusPoints,d.documentDate')
                    ->join('AppBundle:Account', 'a', 'WITH', 'd.accountTo = a')
                    ->join('AppBundle:User', 'u', 'WITH', 'a.user = u')
                    ->join('AppBundle:TransactionType', 't', 'WITH', 'd.transactionType = t')
                    ->where('u =:customer')
                    ->andWhere('t.id =:typeId')
                    ->andWhere('d.documentDate >=:minDate')
                    ->andWhere('d.documentDate <=:maxDate')
                    ->setParameter('customer', $customer[0])
                    ->setParameter('typeId', $type)
                    ->setParameter('minDate', $minDate)
                    ->setParameter('maxDate', $maxDate)
                    ->orderBy('d.documentDate', 'DESC')
                    ->setMaxResults(ConstModel::Limit_For__Document)
                    ->getQuery();
                $docs = $docQuery->getArrayResult();
                array_push($docs, "Type9");
            }
        }
        return new JsonResponse($docs);
    }


    /**
     * @Route("/history/find-branch-history-card-accumulates/{id}", name="findCardHistoryAccumulates")
     */
    public function findCardHistoryAccumulates(Request $request,$id,$_locale="am"){

        $customerId = $id;
        $dateNow = new \DateTime('-1 day');
        $customerRepository = $this->getDoctrine()->getManager()->getRepository('AppBundle:Customer');
        $customer = $customerRepository->findBy(['id' => $customerId]);
        $documentRepository = $this->getDoctrine()->getManager()->getRepository('AppBundle:Document');

        $em = $this->getDoctrine()->getManager();

        if(!isset($customer[0])) {
            $docs = [];
        }else {
            $docs = $em->createQuery(
                   'SELECT d.amount, d.bonusPoints,d.documentDate
                    FROM AppBundle:Document d 
                    JOIN AppBundle:Account a WITH d.accountTo = a 
                    LEFT JOIN AppBundle:User u WITH a.user = u 
                    LEFT JOIN AppBundle:Customer c WITH a.customer = c 
                    JOIN AppBundle:TransactionType t WITH d.transactionType = t 
                    WHERE (c =:customer OR c.user =:user) AND (t.id =:typeId AND d.documentDate >= :now )
                    ORDER BY d.documentDate DESC')
                 ->setParameter('customer', $customer[0])
                 ->setParameter('user', $customer[0]->getUser())
                 ->setParameter('typeId', 9)
                 ->setParameter('now', $dateNow)
                 ->getArrayResult();

            }
        return $this->render(
            'UserBundle:Black:cards-history-accumulates.html.twig',
            array(
                'docs' => $docs,
                'providerId' => $id
            )
        );
    }

    /**
     * @Route("/history/find-branch-history-card-sales/{id}", name="findCardHistorySales")
     */
    public function findCardHistorySales(Request $request,$id,$_locale="am"){

        $customerId = $id;
        $customerRepository = $this->getDoctrine()->getManager()->getRepository('AppBundle:Customer');
        $customer = $customerRepository->findBy(['id' => $customerId]);
        $documentRepository = $this->getDoctrine()->getManager()->getRepository('AppBundle:Document');


//        dump($customer);die;
        if(!isset($customer[0])) {
            $docs = [];
        }else {
            $docQuery = $documentRepository->createQueryBuilder('d')
                ->select('d.amount, d.bonusPoints,d.documentDate')
                ->join('AppBundle:Account', 'a', 'WITH', 'd.accountFrom = a')
                ->join('AppBundle:User', 'u', 'WITH', 'a.user = u')
                ->join('AppBundle:TransactionType', 't', 'WITH', 'd.transactionType = t')
                ->where('u =:customer')
                ->andWhere('t.id =:typeId')
                ->setParameter('customer', $customer[0])
                ->setParameter('typeId', 7)
                ->orderBy('d.documentDate', 'DESC')
                ->setMaxResults(ConstModel::Limit_For__Document)
                ->getQuery();


            $docs = $docQuery->getArrayResult();
        }


        return $this->render(
            'UserBundle:Black:cards-history-sales.html.twig',
            array(
                'docs' => $docs,
                'providerId' => $id
            )
        );
    }



    /**
     * @Route("/history/find-branch-history-card-accumulates", name="findCardHistoryAccumulatesFilter")
     */
    public function findCardHistoryAccumulatesFilter(Request $request,$_locale="am"){

        $minDate   = ($request->get('filterdatemin') != "") ? $request->get('filterdatemin') : date('Y-m-d', strtotime(date('Y-m-d') . "-100 years"));
        $maxDate   = ($request->get('filterdatemax') != "") ? $request->get('filterdatemax') : date('Y-m-d', strtotime(date('Y-m-d') . "+1 day"));
        $customerId = $request->get('customerId');


        $customerRepository = $this->getDoctrine()->getManager()->getRepository('AppBundle:Customer');
        $customer = $customerRepository->findBy(['id' => $customerId]);
        $documentRepository = $this->getDoctrine()->getManager()->getRepository('AppBundle:Document');
        $em = $this->getDoctrine()->getManager();


        $docs = $em->createQuery(
            'SELECT d.amount, d.bonusPoints,d.documentDate
                    FROM AppBundle:Document d 
                    JOIN AppBundle:Account a WITH d.accountTo = a 
                    LEFT JOIN AppBundle:User u WITH a.user = u 
                    LEFT JOIN AppBundle:Customer c WITH a.customer = c 
                    JOIN AppBundle:TransactionType t WITH d.transactionType = t 
                    WHERE (c =:customer OR c.user =:user) AND (t.id =:typeId AND d.documentDate >=:minDate AND d.documentDate <=:maxDate )
                    ORDER BY d.documentDate DESC')
            ->setParameter('customer', $customer[0])
            ->setParameter('user', $customer[0]->getUser())
            ->setParameter('typeId', 9)
            ->setParameter('maxDate', $maxDate)
            ->setParameter('minDate', $minDate)
            ->getArrayResult();
        ;
        return new JsonResponse($docs);
    }

    /**
     * @Route("/history/find-branch-history-card-sales-filter", name="findCardHistorySalesFilter")
     */
    public function findCardHistorySalesFilter(Request $request,$_locale="am"){

        $minDate   = ($request->get('filterdatemin') != "") ? $request->get('filterdatemin') : date('Y-m-d', strtotime(date('Y-m-d') . "-100 years"));
        $maxDate   = ($request->get('filterdatemax') != "") ? $request->get('filterdatemax') : date('Y-m-d', strtotime(date('Y-m-d') . "+1 day"));
        $customerId = $request->get('customerId');

        $customerRepository = $this->getDoctrine()->getManager()->getRepository('AppBundle:Customer');
        $customer = $customerRepository->findBy(['id' => $customerId]);
        $documentRepository = $this->getDoctrine()->getManager()->getRepository('AppBundle:Document');


        $docQuery = $documentRepository->createQueryBuilder('d')
            ->select('d.amount, d.bonusPoints,d.documentDate')

            ->join('AppBundle:Account', 'a', 'WITH', 'd.accountFrom = a')
            ->join('AppBundle:User', 'u', 'WITH', 'a.user = u')
            ->join('AppBundle:TransactionType', 't', 'WITH', 'd.transactionType = t')

            ->where('u =:customer')
            ->andWhere('t.id =:typeId')
            ->andWhere('d.documentDate >=:minDate')
            ->andWhere('d.documentDate <=:maxDate')

            ->setParameter('customer',$customer)
            ->setParameter('typeId',7)
            ->setParameter('maxDate', $maxDate)
            ->setParameter('minDate', $minDate)

            ->orderBy('d.documentDate', 'DESC')
            ->setMaxResults(ConstModel::Limit_For__Document)
            ->getQuery()
        ;

        $docs = $docQuery->getArrayResult();


        return new JsonResponse($docs);
    }

    /**
     * @Route("/history/provider-all-cards/{providerId}", name="providerAllCards")
     */
    public function providerCardHistoryShow(Request $request,$providerId,$_locale="am"){

        $user = $this->getUser();
        $translator = $this->get('translator');
        if (!is_object($user) || !$user instanceof UserInterface) {
            throw new AccessDeniedException($translator->trans('This user does not have access to this section.'));
        }

        $providerRepository = $this->getDoctrine()->getRepository('AppBundle:Provider');
        $provider = $providerRepository->findBy(['user' => $providerId]);

        $customerRepository = $this->getDoctrine()->getRepository('AppBundle:Customer');
        //$cardOfProvider = $customerRepository->findBy(['provider' => $provider[0]]);


        $dateNow = date("Y-m-d");
        $cardOfProvider = $customerRepository->createQueryBuilder('c')
            ->join('AppBundle:Provider','p','WITH','c.provider = p')
            ->where('c.cardRegisteredAt >=:dateNow')
            ->andWhere('p =:providerAccount')
            ->setParameter('dateNow',$dateNow)
            ->setParameter('providerAccount',$provider[0])
            ->getQuery()->getResult();


        return $this->render('UserBundle:Black:provider-cards-list.html.twig',array(
            'provider' => $provider[0],
            'cards'    => $cardOfProvider,
            'providerId' => $providerId
        ));
    }

    /**
     * @Route("/history/provider-withdraw/{providerId}", name="providerWithdraw")
     */
    public function providerWithdraw(Request $request,$providerId,$_locale="am"){
        $user = $this->getUser();
        $translator = $this->get('translator');
        if (!is_object($user) || !$user instanceof UserInterface) {
            throw new AccessDeniedException($translator->trans('This user does not have access to this section.'));
        }

        $documentRepository = $this->getDoctrine()->getRepository('AppBundle:Document');

        $userRepository = $this->getDoctrine()->getRepository('AppBundle:User');
        $userProvider = $userRepository->findBy(['id' => $providerId]);


        $dateNow = date("Y-m-d");
        $providerWithdraws = $documentRepository->createQueryBuilder('d')
            ->select('d,ul.username')
            ->join('AppBundle:Account', 'a', 'WITH', 'd.accountFrom = a')
            ->join('AppBundle:User', 'u', 'WITH', 'a.user = u')
            ->join('AppBundle:User', 'ul', 'WITH', 'd.userDoneWithdraw = ul')
            ->join('AppBundle:TransactionType', 't', 'WITH', 'd.transactionType = t')
            ->where('u =:account')
            ->andWhere('t =:type')
            ->andwhere('d.documentDate >=:dateNow')
            ->setParameter('account', $userProvider[0])
            ->setParameter('dateNow',$dateNow)
            ->setParameter('type', 13)
            ->getQuery()
            ->getArrayResult()
        ;
//        dump($providerWithdraws);die;

        return $this->render('UserBundle:Black:provider-withdraw-list.html.twig',array(
            'providerWithdraws' => $providerWithdraws,
            'providerId'        => $providerId
        ));
    }

    /**
     * @Route("/history/provider-withdraw-filter", name="providerWithdrawFilter")
     */
    public function providerWithdrawFilter(Request $request,$_locale="am"){
        $minDate   = ($request->get('filterdatemin') != "") ? $request->get('filterdatemin') : date('Y-m-d', strtotime(date('Y-m-d') . "-100 years"));
        $maxDate   = ($request->get('filterdatemax') != "") ? $request->get('filterdatemax') : date('Y-m-d', strtotime(date('Y-m-d') . "+1 day"));
        $providerId = $request->get('provider_id');

        $documentRepository = $this->getDoctrine()->getRepository('AppBundle:Document');

        $userRepository = $this->getDoctrine()->getRepository('AppBundle:User');
        $userProvider = $userRepository->findBy(['id' => $providerId]);

        $providerWithdraws = $documentRepository->createQueryBuilder('d')
            ->select('d,ul.username')
            ->join('AppBundle:Account', 'a', 'WITH', 'd.accountFrom = a')
            ->join('AppBundle:User', 'u', 'WITH', 'a.user = u')
            ->join('AppBundle:User', 'ul', 'WITH', 'd.userDoneWithdraw = ul')
            ->join('AppBundle:TransactionType', 't', 'WITH', 'd.transactionType = t')
            ->where('u =:account')
            ->andWhere('d.documentDate >=:minDate')
            ->andWhere('d.documentDate <=:maxDate')
            ->andWhere('t =:type')
            ->setParameter('account', $userProvider[0])
            ->setParameter('type', 13)
            ->setParameter('minDate', $minDate)
            ->setParameter('maxDate', $maxDate)
            ->getQuery()
            ->getArrayResult()
        ;
        return new JsonResponse($providerWithdraws);

    }

    /**
     * @Route("/history/provider-all-cards-filter", name="providerAllCardsFilter")
     */
    public function providerAllCardsFilter(Request $request,$_locale="am"){


        $minDate   = ($request->get('filterdatemin') != "") ? $request->get('filterdatemin') : date('Y-m-d', strtotime(date('Y-m-d') . "-100 years"));
        $maxDate   = ($request->get('filterdatemax') != "") ? $request->get('filterdatemax') : date('Y-m-d', strtotime(date('Y-m-d') . "+1 day"));
        $providerId = $request->get('provider_id');

        $providerRepository = $this->getDoctrine()->getRepository('AppBundle:Provider');
        $provider = $providerRepository->findBy(['user' => $providerId]);
        $customerRepository = $this->getDoctrine()->getRepository('AppBundle:Customer');

        $cardOfProvider = $customerRepository->createQueryBuilder('c')
            ->select('c.customerCardNumber,c.customerFirstName,c.customerLastName,c.customerPhone,c.customerEmail,c.cardRegisteredAt,acc.id,u.id AS cuser')
            ->leftJoin('AppBundle:User', 'u', 'WITH', 'c.user = u')
            ->leftJoin('AppBundle:Account', 'acc', 'WITH', 'acc.user = u')

            ->where('c.cardRegisteredAt >=:minDate')
            ->andWhere('c.cardRegisteredAt <=:maxDate')
            ->andWhere('c.provider =:account')

            ->setParameter('minDate', $minDate)
            ->setParameter('maxDate', $maxDate)
            ->setParameter('account', $provider)
            ->getQuery()
        ;


        $cardsOfProvider = $cardOfProvider->getArrayResult();



        return new JsonResponse($cardsOfProvider);
    }

    /**
     * @Route("/history/provider-all-withdraw/{providerId}", name="providerAllWithDraw")
     */
    public function providerWithdrawHistoryShow(Request $request,$providerId,$_locale="am"){
        $user = $this->getUser();
        $translator = $this->get('translator');
        if (!is_object($user) || !$user instanceof UserInterface) {
            throw new AccessDeniedException($translator->trans('This user does not have access to this section.'));
        }

        $providerRepository = $this->getDoctrine()->getRepository('AppBundle:Provider');
        $provider = $providerRepository->findBy(['user' => $providerId]);

        $customerRepository = $this->getDoctrine()->getRepository('AppBundle:Customer');
        $cardOfProvider = $customerRepository->findBy(['provider' => $provider[0]]);


        return $this->render('UserBundle:Black:provider-cards-list.html.twig',array(
            'provider' => $provider[0],
            'cards'    => $cardOfProvider,
            'providerId' => $providerId
        ));
    }



    /**
     * @Route("/history/provider-current-card-history/{providerId}/{userId}", name="providerCurrentCardHistory")
     */
    public function providerCurrentCardHistory(Request $request,$providerId,$userId,$_locale="am"){
        $documentRepository = $this->getDoctrine()->getRepository('AppBundle:Document');
        $accountRepo = $this->getDoctrine()->getRepository('AppBundle:Account');

        $account = $accountRepo->findBy(['id'=>$userId]);

        $dateNow = date("Y-m-d");
        /** done */
        $documentsSales = $documentRepository->createQueryBuilder('d')
                ->join('AppBundle:Account', 'a', 'WITH', 'd.accountFrom = a')
                ->join('AppBundle:User', 'u', 'WITH', 'a.user = u')
                ->join('AppBundle:TransactionType', 'tt', 'WITH', 'd.transactionType = tt')
                ->where('a = :account')
                ->andWhere('tt = :type')
                ->andWhere('d.documentDate >=:dateNow')
                ->setParameter('account',$account[0])
                ->setParameter('type',7)
                ->setParameter('dateNow',$dateNow)
            ->getQuery()
            ->getResult();

        $documentsAccumulates = $documentRepository->createQueryBuilder('d')
                ->join('AppBundle:Account', 'a', 'WITH', 'd.accountTo = a')
                ->join('AppBundle:User', 'u', 'WITH', 'a.user = u')
                ->join('AppBundle:TransactionType', 'tt', 'WITH', 'd.transactionType = tt')
                ->where('a = :account')
                ->andWhere('tt = :type')
                ->andWhere('d.documentDate >=:dateNow')
                ->setParameter('account',$account[0])
                ->setParameter('type',9)
                ->setParameter('dateNow',$dateNow)
            ->getQuery()
            ->getResult();



        return $this->render('UserBundle:Black:provider-card-view.html.twig',array(
            'providerId'      =>        $providerId,
            'documentsSales'  =>        $documentsSales,
            'documentsAccumulates' =>   $documentsAccumulates,
            'userId'    => $userId
        ));
    }

    /**
     * @Route("/history/provider-current-card-history-filter-show-accumulates", name="providerCurrentCardHistoryShowAccumulates")
     */
    public function getCurrentCardHistoryWithDatesAccumulates(Request $request,$_locale="am"){

        $minDate   = ($request->get('filterdatemin') != "") ? $request->get('filterdatemin') : date('Y-m-d', strtotime(date('Y-m-d') . "-100 years"));
        $maxDate   = ($request->get('filterdatemax') != "") ? $request->get('filterdatemax') : date('Y-m-d', strtotime(date('Y-m-d') . "+1 day"));
        $userId = $request->get('user_id');


        $em = $this->getdoctrine()->getManager();
        $connection = $em->getConnection();

        $statement = $connection->prepare("
                                SELECT u.first_name AS toAc,u.email,d.amount,d.bonus_points,d.document_date,us.username AS fromAc  FROM document d
                                JOIN `account` a ON a.id=d.account_to_id
                                JOIN `account` acc ON acc.id=d.account_from_id
                                JOIN `user` u ON u.id=a.user_id
                                JOIN `user` us ON us.id=acc.user_id
                                WHERE d.transaction_type_id = 9 AND a.id =:userId AND d.document_date >=:minDate AND d.document_date <=:maxDate
        ");
        $statement->bindValue('userId', $userId);
        $statement->bindValue('minDate', $minDate);
        $statement->bindValue('maxDate', $maxDate);
        $statement->execute();
        $documentsAccumulates = $statement->fetchAll();


        return new JsonResponse($documentsAccumulates);
    }



    /**
     * @Route("/data/provider-history-filter", name="providerHistoryFilter")
     * @Method("POST")
     */
    public function providerHistoryFilter(Request $request)
    {
        $user = $this->getUser();
        $minDate   = ($request->get('filterdatemin') != "") ? $request->get('filterdatemin') : date('Y-m-d', strtotime(date('Y-m-d') . "-100 years"));
        $maxDate   = ($request->get('filterdatemax') != "") ? $request->get('filterdatemax') : date('Y-m-d', strtotime(date('Y-m-d') . "+1 day"));

        $documentRepository=$this->getDoctrine()->getRepository('AppBundle:Document');
        $documents=$documentRepository->createQueryBuilder('d')
            ->select('t.transactionAmount,d')
            ->join('AppBundle:Transaction', 't', 'WITH', 't.document = d')
            ->where('d.accountProviderTo=:account')
            ->andWhere('t.account =:account')
            ->andWhere('t.account =:account')

            ->andWhere('d.documentDate >=:minDate')
            ->andWhere('d.documentDate <=:maxDate')

            ->setParameter('maxDate', $maxDate)
            ->setParameter('minDate', $minDate)
            ->setParameter('account',$user->getAccount())

            ->getQuery()
            ->getArrayResult();
        ;

        return new JsonResponse($documents);
    }


    /**
     * @Route("/history/provider-current-card-history-filter-show-sales", name="providerCurrentCardHistoryShowSales")
     */
    public function getCurrentCardHistoryWithDatesSales(Request $request,$_locale="am"){
        $minDate   = ($request->get('filterdatemin') != "") ? $request->get('filterdatemin') : date('Y-m-d', strtotime(date('Y-m-d') . "-100 years"));
        $maxDate   = ($request->get('filterdatemax') != "") ? $request->get('filterdatemax') : date('Y-m-d', strtotime(date('Y-m-d') . "+1 day"));
        $userId = $request->get('user_id');

        $em = $this->getdoctrine()->getManager();
        $connection = $em->getConnection();

        $statement = $connection->prepare("
                    SELECT us.first_name AS fromAc,us.email,d.amount,d.bonus_points,d.document_date,b.branch_title AS toAc
                    FROM `document` d
                    JOIN `account` a ON a.id=d.account_to_id
                    JOIN `account` acc ON acc.id=d.account_from_id
                    JOIN `branch` b ON a.branch_id = b.id
                    JOIN `user` us ON us.id=acc.user_id
                    WHERE d.transaction_type_id=7 AND acc.id =:userId AND d.document_date >=:minDate AND d.document_date <=:maxDate
            ");
        $statement->bindValue('userId', $userId);
        $statement->bindValue('minDate', $minDate);
        $statement->bindValue('maxDate', $maxDate);
        $statement->execute();
        $documentsSales = $statement->fetchAll();


        return new JsonResponse($documentsSales);
    }
}