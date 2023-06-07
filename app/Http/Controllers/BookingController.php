<?php

namespace DTApi\Http\Controllers;

use DTApi\Http\Requests;
use Illuminate\Http\Request;
use DTApi\Repository\BookingRepository;

/**
 * Class BookingController
 * @package DTApi\Http\Controllers
 */
class BookingController extends Controller
{

    /**
     * @var BookingRepository
     */
    protected $repository;

    /**
     * BookingController constructor.
     * @param BookingRepository $bookingRepository
     */
    public function __construct(BookingRepository $bookingRepository)
    {
        $this->repository = $bookingRepository;
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function index(Request $request)
    {
        if($user_id = (int) $request->input('user_id')) {

            $response = $this->repository->getUsersJobs($user_id);

        }
        elseif($request->__authenticatedUser->user_type == env('ADMIN_ROLE_ID') || $request->__authenticatedUser->user_type == env('SUPERADMIN_ROLE_ID'))
        {
            $response = $this->repository->getAll($request);
        }

        return response()->json($response);
    }

    /**
     * @param $id
     * @return mixed
     */
    public function show($id)
    {
        $job = $this->repository->with('translatorJobRel.user')->find($id);

        return response()->json($job);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function store(Request $request)
    {
        $data = $request->all();

        $validator = $request->validated();
        if($validator->fails()) return response()->json($validator, 403);
        $response = $this->repository->store($request->__authenticatedUser, $data);

        return response()->json($response, 201);

    }

    /**
     * @param $id
     * @param Request $request
     * @return mixed
     */
    public function update($id, Request $request)
    {
        $data = $request->all();
        $cuser = $request->__authenticatedUser;
        $response = $this->repository->updateJob($id, array_except($data, ['_token', 'submit']), $cuser);

        return response()->json($response);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function immediateJobEmail(Request $request)
    {
        $adminSenderEmail = config('app.adminemail');
        $data = $request->all();

        $response = $this->repository->storeJobEmail($data);

        return response()->json($response);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function getHistory(Request $request)
    {
        if($user_id = (int) $request->input('user_id')) {

            $response = $this->repository->getUsersJobsHistory($user_id, $request);
            return response()->json($response);
        }

        return null;
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function acceptJob(Request $request)
    {
        $data = $request->all();
        $user = $request->__authenticatedUser;

        $response = $this->repository->acceptJob($data, $user);

        return response()->json($response);
    }

    public function acceptJobWithId(Request $request)
    {
        $data = $request->input('job_id');
        $user = $request->__authenticatedUser;

        $response = $this->repository->acceptJobWithId($data, $user);

        return response()->json($response, 201);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function cancelJob(Request $request)
    {
        $data = $request->all();
        $user = $request->__authenticatedUser;

        $response = $this->repository->cancelJobAjax($data, $user);

        return response()->json($response);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function endJob(Request $request)
    {
        $data = $request->all();

        $response = $this->repository->endJob($data);

        return response()->json($response);

    }

    public function customerNotCall(Request $request)
    {
        $data = $request->all();

        $response = $this->repository->customerNotCall($data);

        return response()->json($response);

    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function getPotentialJobs(Request $request)
    {
        // $data = $request->all();  this is not in use
        $user = $request->__authenticatedUser;

        $response = $this->repository->getPotentialJobs($user);

        return response()->json($response);
    }

    public function distanceFeed(Request $request)
    {
        $data = $request->all();
        $validator = $request->validated([
            'admincomments' => 'required',
        ]);
            if($validator->fails()){
                return response()->json($validator, 403);
            }
        extract($data);
        $distanced =  $distance ?? "";
        $times = $time ??  "";
        $job_id = $jobid ?? '';
        $session = $session_time ??  "";

//        if(empty($admincomment)) return response()->json("Please, add comment", 403);

        $flag = ($flagged) ? 'yes' : 'no';
        $manually_handled_data = ($manually_handled) ? 'yes' : 'no';  //if else block replaced with Ternary operator
        $by_admins = ($by_admin) ? 'yes' : 'no';

        $admincomments = $admincomment ?? "";
        if ($times || $distanced) {
            $this->repository->updateDistance( $job_id, $distanced, $times);
        }

        if ($admincomments || $session || $flag || $manually_handled_data || $by_admins) {

            $affectedRows1 = $this->repository->updateJobs($job_id, $admincomments, $flag, $session, $manually_handled_Data, $by_admins);

            if($affectedRows1)
                return response()->json('Record updated!');

            return response()->json('Record is not updated', 403);
        }

    }

    public function reopen(Request $request)
    {
        $data = $request->all();
        $response = $this->repository->reopen($data);

        return response()->json($response);
    }

    public function resendNotifications(Request $request)
    {
        $jobid = (int) $request->input('jobid');  // getting single variable data from request
        $job = $this->repository->find($jobid);
        $job_data = $this->repository->jobToData($job);
        $response = $this->repository->sendNotificationTranslator($job, $job_data, '*');

        return ($response) ? response()->json(['success' => 'Push sent']) : response()->json(['success' => 'Push not sent']);
    }

    /**
     * Sends SMS to Translator
     * @param Request $request
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Symfony\Component\HttpFoundation\Response
     */
    public function resendSMSNotifications(Request $request)
    {
        $jobid = $request->input('jobid');  // geting single variable which is required
        $job = $this->repository->find($jobid);
//        $job_data = $this->repository->jobToData($job);

        try {
            $count = $this->repository->sendSMSNotificationToTranslator($job);
            return ($count) ? response()->json(['success' => 'SMS sent']) : response()->json(['error' => 'SMS not sent']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        }
    }

}