<?php

namespace App\Http\Controllers;
use App\Cases;
use function App\Helpers\checkMilestoneID;
use function App\Helpers\checkParams;
use function App\Helpers\checkProjectID;
use function App\Helpers\decryptData;
use function App\Helpers\encArray;
use function App\Helpers\encryptData;
use function App\Helpers\getClientFromDomain;
use function App\Helpers\getControllerName;
use function App\Helpers\getDataWithPageNo;
use function App\Helpers\paramExist;
use App\Http\ApiTracker;
use App\Milestone;
use App\Project;
use App\ProjectRole;
use App\Statuses;
use App\TestRun;
use App\TestRunHistory;
use App\TestRunResult;
use App\TestRunStepResult;
use App\Casessteps;
use Carbon\Carbon;
use Illuminate\Http\Request;

class TestRunController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /*
     * function to check whether the test run is present for the given project
     *  @params
     *  1. test run id, 2.project id
     */
    public function checkTestRunID($_testRunID,$_projectID){

        $available = false;
        // decrypt the test run id passedupdateStatusTestRunResults
        $currTestRunID = decryptData($_testRunID);
        $currTestRun = TestRun::where('test_run_id','=',$currTestRunID)
            ->where('project_id','=',decryptData($_projectID))
            ->first();

        if($currTestRun){
            $available = true;
        }
        return $available;
    }

    /*
     * function to check whether the test run result key is available in the project
     *
     * @params
     * 1. projectID, 2. testRunID, 3.testRunResultID
     */
    public function checkTestRunResultsID($_projectID, $_testRunID, $testRunResultID){

        $available = false;

        $currTestRun = TestRunResult::where('run_id','=',decryptData($testRunResultID))
            ->where('project_id','=',decryptData($_projectID))
            ->where('test_run_id','=',decryptData($_testRunID))
            ->first();

        if($currTestRun){
            $available = true;
        }
        return $available;

    }

    /*
     * function to get all the status available
     */
    public function getAllStatuses(Request $request){

        $method = $request->method();
        $allStatuses = Statuses::where('status','=','1')
                    ->selectRaw('statuses_id as `status_key`,'.
                                'statuses_name as `status`')
                    ->get()
                    ->toArray();

        // for replacing all the spaces with a hypen
        // for making it easier for user to update using the name
        // as spaces are not encouraged in URL parameters
        foreach($allStatuses as &$allStatus){

            $allStatus['status'] = preg_replace('/\s+/', '-', $allStatus['status']);

        }

        // TRACKER
        if(getControllerName($request->route()[1]['uses'])[1] == 'getAllStatuses'){
            ApiTracker::trackRequest([
                'clientID' => getClientFromDomain($request->header('domain')),
                'projectID' => null,
                'userID' => \Auth::user()->user_id,
                'controller' =>  getControllerName($request->route()[1]['uses'])[0],
                'method' => getControllerName($request->route()[1]['uses'])[1],
                'status' => 'SUCCESS',
                'route' => $request->method()
            ]);

        }

        return response(['success' => true, 'data' => $allStatuses]);


    }
    /*
     * function to get all the test case for a  project
     * @params projectID, pageNo(optional)
     * returns a set of test runs for the project
     */
    public function getAllTestRuns(Request $request,$projectID,$status = null){

        $method = $request->method();
        // decrypt the project id passed
        $currProjectID = decryptData($projectID);
        //get the page number from the request params
        $pageNo = $request->input('page');
        // check if the project ID passed is available in the projects
        $currProject = Project::where('project_id','=',$currProjectID)->first();

        // if the project id is available
        if($currProject){

            // then get all the cases for that particular project
            $allTestRuns = TestRun::where('project_id','=',$currProjectID)
                    ->where('client_id','=',$currProject->client_id)
                    ->selectRaw('name as `testrun_name`,'.
                        'test_run_id as `testrun_key`')
                    ->where('qa_testrun.status','=', 1)
                    ->get()
                    ->chunk(50);

            // TRACKER
            ApiTracker::trackRequest([
                'clientID' => getClientFromDomain($request->header('domain')),
                'projectID' => $currProjectID,
                'userID' => \Auth::user()->user_id,
                'controller' =>  getControllerName($request->route()[1]['uses'])[0],
                'method' => getControllerName($request->route()[1]['uses'])[1],
                'status' => 'SUCCESS',
                'route' => $request->method()
            ]);

            // check if the query is not empty
            if(count($allTestRuns) > 0){

                $allTestRuns = encArray($allTestRuns,'testrun_key');

                return getDataWithPageNo($allTestRuns,$pageNo);

            }
            // if empty
            else{
                // return no found message
                return response(['msg' => "No Test Run(s) found for the project!"]);

            }

        }

        // TRACKER
        ApiTracker::trackRequest([
            'clientID' => getClientFromDomain($request->header('domain')),
            'projectID' => $projectID,
            'userID' => \Auth::user()->user_id,
            'controller' =>  getControllerName($request->route()[1]['uses'])[0],
            'method' => getControllerName($request->route()[1]['uses'])[1],
            'status' => 'Project Key not found',
            'route' => $request->method()
        ]);
        // if the project id passed is not available then throw error
        return response(['msg' => "Project Key not found"]);

    }

    /*
     * function to get the count of the all test runs
     *  based on the project id
     */
    public function countAllTestRuns(Request $request, $projectID){

        $method = $request->method();
        // decrypt the project id passed
        $currProjectID = decryptData($projectID);
        // check if the project ID passed is available in the projects
        $currProject = Project::where('project_id','=',$currProjectID)->first();

        // if the project id is available
        if($currProject){

            // then get all the cases for that particular project
            $allTestRuns = TestRun::where('project_id','=',$currProjectID)
                ->where('client_id','=',$currProject->client_id)
                ->selectRaw('name as `testrun_name`,'.
                    'test_run_id as `testrun_key`')
                ->where('qa_testrun.status','=', 1)
                ->count();

            // TRACKER
            ApiTracker::trackRequest([
                'clientID' => getClientFromDomain($request->header('domain')),
                'projectID' => $currProjectID,
                'userID' => \Auth::user()->user_id,
                'controller' =>  getControllerName($request->route()[1]['uses'])[0],
                'method' => getControllerName($request->route()[1]['uses'])[1],
                'status' => 'SUCCESS',
                'route' => $request->method()
            ]);

            return response(['count' => $allTestRuns]);

        }

        // TRACKER
        ApiTracker::trackRequest([
            'clientID' => getClientFromDomain($request->header('domain')),
            'projectID' => $projectID,
            'userID' => \Auth::user()->user_id,
            'controller' =>  getControllerName($request->route()[1]['uses'])[0],
            'method' => getControllerName($request->route()[1]['uses'])[1],
            'status' => 'Project Key not found',
            'route' => $request->method()
        ]);

        // if the project id passed is not available then throw error
        return response(['msg' => "Project Key not found"]);
    }


    /*
     * function to get all the test run results based on the test run id
     * @params
     *      1. Project id, test run key
     * returns
     *      testrunkey,title,status,assigned_user
     */
    public function testRunResults(Request $request,$projectID, $testRunID){

        $method = $request->method();
        //get the page number from the request params
        $pageNo = $request->input('page');

        // if the project id is available
        if(checkProjectID($projectID)){

            // if the test run key is available
            if($this->checkTestRunID($testRunID,$projectID)){

                // fetch all the test run results for given
                // 1. project id, test run id
                $allTestRunResult = TestRunResult::leftJoin('qa_users','qa_users.id','=','qa_testrun_result.assign_user')
                                ->join('qa_cases','qa_cases.case_id','=','qa_testrun_result.case_id')
                                ->leftJoin('qa_statuses','qa_statuses.statuses_id','=','qa_testrun_result.status_id')
                                ->where('qa_testrun_result.project_id','=',decryptData($projectID))
                                ->where('qa_testrun_result.test_run_id','=',decryptData($testRunID))
                                ->selectRaw('run_id as `run_key`,'.
                                            'qa_cases.title as `title`,'.
                                            'qa_statuses.statuses_name as `status`,'.
                                           //'COALESCE(CONCAT(firstname," ",lastname),"USAMA REHAN") as `assigned_user`')
                                            'CONCAT(firstname," ",lastname) as `assigned_user`')
                                ->where('qa_testrun.status','=', 1)
                                ->orderBy('status_change_date','desc')
                                ->get()
                                ->chunk(50);

                // TRACKER
                ApiTracker::trackRequest([
                    'clientID' => getClientFromDomain($request->header('domain')),
                    'projectID' => decryptData($projectID),
                    'userID' => \Auth::user()->user_id,
                    'controller' =>  getControllerName($request->route()[1]['uses'])[0],
                    'method' => getControllerName($request->route()[1]['uses'])[1],
                    'status' => 'SUCCESS',
                    'route' => $request->method()
                ]);

                // check if the query is not empty
                if(count($allTestRunResult) > 0){

                    $allTestRuns = encArray($allTestRunResult,'run_key');

                    return getDataWithPageNo($allTestRuns,$pageNo);

                }
                // if empty
                else{
                    // return no found message
                    return response(['msg' => "No Test Run Result(s) found for the project!"]);

                }

            }

            // TRACKER
            ApiTracker::trackRequest([
                'clientID' => getClientFromDomain($request->header('domain')),
                'projectID' => decryptData($projectID),
                'userID' => \Auth::user()->user_id,
                'controller' =>  getControllerName($request->route()[1]['uses'])[0],
                'method' => getControllerName($request->route()[1]['uses'])[1],
                'status' => 'Wrong Test Run Key',
                'route' => $request->method()
            ]);

            // if the test run id passed is not available then throw error
            return response(['msg' => "Test Run Key not found"]);

        }
        // TRACKER
        ApiTracker::trackRequest([
            'clientID' => getClientFromDomain($request->header('domain')),
            'projectID' => $projectID,
            'userID' => \Auth::user()->user_id,
            'controller' =>  getControllerName($request->route()[1]['uses'])[0],
            'method' => getControllerName($request->route()[1]['uses'])[1],
            'status' => 'Project Key not found',
            'route' => $request->method()
        ]);
        // if the project id passed is not available then throw error
        return response(['msg' => "Project Key not found"]);

    }

    /**
     * @param Request $request
     * @param $projectID
     * @param $milestoneID
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function milestoneTestRunResults(Request $request,$projectID, $milestoneID) {

        //get the page number from the request params
        $pageNo = $request->input('page');

        if(!checkProjectID($projectID)){
            // TRACKER
            ApiTracker::trackRequest([
                'clientID' => getClientFromDomain($request->header('domain')),
                'projectID' => $projectID,
                'userID' => \Auth::user()->user_id,
                'controller' =>  getControllerName($request->route()[1]['uses'])[0],
                'method' => getControllerName($request->route()[1]['uses'])[1],
                'status' => 'Project Key not found',
                'route' => $request->method()
            ]);

            // if the project id passed is not available then throw error
            return response(['msg' => "Project Key not found"]);
        }

        if(!checkMilestoneID($milestoneID)){
            // TRACKER
            ApiTracker::trackRequest([
                'clientID' => getClientFromDomain($request->header('domain')),
                'projectID' => $projectID,
                'userID' => \Auth::user()->user_id,
                'controller' =>  getControllerName($request->route()[1]['uses'])[0],
                'method' => getControllerName($request->route()[1]['uses'])[1],
                'status' => 'Milestone Key not found',
                'route' => $request->method()
            ]);
            // if the project id passed is not available then throw error
            return response(['msg' => "Milestone Key not found"]);
        }

        $allTestRunResult = TestRun::join('qa_testrun_result','qa_testrun_result.test_run_id','=','qa_testrun.test_run_id')
                            ->leftJoin('qa_users','qa_users.id','=','qa_testrun_result.assign_user')
                            ->join('qa_cases','qa_cases.case_id','=','qa_testrun_result.case_id')
                            ->leftJoin('qa_statuses','qa_statuses.statuses_id','=','qa_testrun_result.status_id')
                            ->where('milestone_id','=', decryptData($milestoneID))
                            ->where('qa_testrun.status','=', 1)
                                ->selectRaw('run_id as `run_key`,'.
                                    'qa_cases.title as `title`,'.
                                    'qa_statuses.statuses_name as `status`,'.
                                    'CONCAT(firstname," ",lastname) as `assigned_user`');

        if($request->filled('status')){

            $allStatus = [];
            $onlyStatusKey = [];
            // get the all the status and extract the data from the response
            $tempStatus = $this->getAllStatuses($request)->original['data'];
            // take out only the status from the data
            // by making all the status in lower case
            foreach($tempStatus as $allAvailableStatus){
                array_push($allStatus,[$allAvailableStatus['status_key'] => strtolower($allAvailableStatus['status'])]);
                array_push($onlyStatusKey,strtolower($allAvailableStatus['status']));
            }
            $askedStatusID = -1;
            foreach($allStatus as $key => $status){
                if(strtolower($request->input('status')) == strtolower(reset($status))){
                    $askedStatusID = key($status);
                }
            }
            if(!in_array(strtolower($request->input('status')), $onlyStatusKey)){
                // TRACKER
                ApiTracker::trackRequest([
                    'clientID' => getClientFromDomain($request->header('domain')),
                    'projectID' => $projectID,
                    'userID' => \Auth::user()->user_id,
                    'controller' =>  getControllerName($request->route()[1]['uses'])[0],
                    'method' => getControllerName($request->route()[1]['uses'])[1],
                    'status' => 'Status not found',
                    'route' => $request->method()
                ]);
                // if the project id passed is not available then throw error
                return response(['msg' => "Status Key not found"]);
            }

            $allTestRunResult = $allTestRunResult->where('qa_statuses.statuses_id','=',$askedStatusID)
                                ->orderBy('status_change_date','desc')
                                ->get()
                                ->chunk(50);

        }
        else{
            $allTestRunResult = $allTestRunResult->orderBy('status_change_date','desc')
                                ->get()
                                ->chunk(50);
        }


        // TRACKER
        ApiTracker::trackRequest([
            'clientID' => getClientFromDomain($request->header('domain')),
            'projectID' => decryptData($projectID),
            'userID' => \Auth::user()->user_id,
            'controller' =>  getControllerName($request->route()[1]['uses'])[0],
            'method' => getControllerName($request->route()[1]['uses'])[1],
            'status' => 'SUCCESS',
            'route' => $request->method()
        ]);

        // check if the query is not empty
        if(count($allTestRunResult) > 0){

            $allTestRuns = encArray($allTestRunResult,'run_key');
            return getDataWithPageNo($allTestRuns,$pageNo);

        }
        // if empty
        else{
            // return no found message
            return response(['msg' => "No Test Run Result(s) found for the project!"]);
        }

    }

    /*
     * function to get all the history of the test run result
     *
     * @params
     *  1.project key, 2.test run key 3.test run result key
     * returns,
     *      history_key, title, status, comments, assigned_user
     */
    public function testRunResultsHistory(Request $request,$projectID, $testRunID, $testRunResultID){

        $method = $request->method();
        //get the page number from the request params
        $pageNo = $request->input('page');

        // if the project id is available
        if(checkProjectID($projectID)){
            // check if the test run key provided is correct
            if($this->checkTestRunID($testRunID,$projectID)){

                // check if the test run result key provided is correct
                if($this->checkTestRunResultsID($projectID, $testRunID, $testRunResultID)){

                    // query out the required data from the test run history for the given
                    // 1. test run key, 2.test run result key
                    $allTestRunResultHistory = TestRunHistory::leftJoin('qa_users','qa_users.id','=','qa_testrun_history.assign_user')
                        ->join('qa_cases','qa_cases.case_id','=','qa_testrun_history.case_id')
                        ->leftJoin('qa_statuses','qa_statuses.statuses_id','=','qa_testrun_history.status_id')
                        ->where('result_id','=',decryptData($testRunResultID))
                        ->where('test_run_id','=',decryptData($testRunID))
                        ->selectRaw('history_id as  `history_key`,'.
                            'qa_cases.title as `title`,'.
                            'qa_statuses.statuses_name as `status`,'.
                            'comments as `comments`,'.
                            'CONCAT(firstname," ",lastname) as `assigned_user`')
                        ->orderBy('qa_testrun_history.history_id','asc')
                        ->get()
                        ->chunk(50);

                    // TRACKER
                    ApiTracker::trackRequest([
                        'clientID' => getClientFromDomain($request->header('domain')),
                        'projectID' => decryptData($projectID),
                        'userID' => \Auth::user()->user_id,
                        'controller' =>  getControllerName($request->route()[1]['uses'])[0],
                        'method' => getControllerName($request->route()[1]['uses'])[1],
                        'status' => 'SUCCESS',
                        'route' => $request->method()
                    ]);

                    // check if the query is not empty
                    if(count($allTestRunResultHistory) > 0){

                        $allTestRuns = encArray($allTestRunResultHistory,'history_key');

                        return getDataWithPageNo($allTestRuns,$pageNo);

                    }
                    // if empty
                    else{
                        // return no found message
                        return response(['msg' => "No Result History(s) found for the project!"]);

                    }

                }

                // TRACKER
                ApiTracker::trackRequest([
                    'clientID' => getClientFromDomain($request->header('domain')),
                    'projectID' => decryptData($projectID),
                    'userID' => \Auth::user()->user_id,
                    'controller' =>  getControllerName($request->route()[1]['uses'])[0],
                    'method' => getControllerName($request->route()[1]['uses'])[1],
                    'status' => 'Wrong Test Run Result Key',
                    'route' => $request->method()
                ]);
                // if the test run key passed is not available then throw error
                return response(['msg' => "No Test Run Result key not Found!"]);

            }

            // TRACKER
            ApiTracker::trackRequest([
                'clientID' => getClientFromDomain($request->header('domain')),
                'projectID' => decryptData($projectID),
                'userID' => \Auth::user()->user_id,
                'controller' =>  getControllerName($request->route()[1]['uses'])[0],
                'method' => getControllerName($request->route()[1]['uses'])[1],
                'status' => 'Wrong Test Run Key',
                'route' => $request->method()
            ]);
            // if the test run  id passed is not available then throw error
            return response(['msg' => "Test Run Key not found"]);
        }
        // TRACKER
        ApiTracker::trackRequest([
            'clientID' => getClientFromDomain($request->header('domain')),
            'projectID' => $projectID,
            'userID' => \Auth::user()->user_id,
            'controller' =>  getControllerName($request->route()[1]['uses'])[0],
            'method' => getControllerName($request->route()[1]['uses'])[1],
            'status' => 'Project Key not found',
            'route' => $request->method()
        ]);

        // if the project id passed is not available then throw error
        return response(['msg' => "Project Key not found"]);
    }

    /*
     * fuction to update the status of the test run results
     * @params
     *  1.project_id, 2.test_run, 3.result_id, 4.status(to be updated)
     */
    public function updateStatusTestRunResults(Request $request){

        $method = $request->method();
        $requiredParams = ['status','project','test_run','run_result'];

        $request->input($requiredParams[0]);
        // checking the parameter for its value and key availability
        $resultParam = paramExist($request->input(), $requiredParams);

        if($resultParam != null){

            // TRACKER
            ApiTracker::trackRequest([
                'clientID' => getClientFromDomain($request->header('domain')),
                'projectID' => null,
                'userID' => \Auth::user()->user_id,
                'controller' =>  getControllerName($request->route()[1]['uses'])[0],
                'method' => getControllerName($request->route()[1]['uses'])[1],
                'status' => $resultParam,
                'route' => $request->method()
            ]);

            return response(['success' => false, 'error_msg' => $resultParam]);

        }

        // if the project id is available
        if(checkProjectID($request->input($requiredParams[1]))) {
            // check if the test run key provided is correct
            if ($this->checkTestRunID($request->input($requiredParams[2]), $request->input($requiredParams[1]))) {
                // check if the test run id provided is correct and available
               if($this->checkTestRunResultsID($request->input($requiredParams[1]), $request->input($requiredParams[2]), $request->input($requiredParams[3]))){

                   $allStatus = [];
                   // get the all the status and extract the data from the response
                   $tempStatus = $this->getAllStatuses($request)->original['data'];
                    // take out only the status from the data
                   // by making all the status in lower case
                   foreach($tempStatus as $allAvailableStatus){
                       array_push($allStatus,strtolower($allAvailableStatus['status']));
                   }

                   // check if the status provided is available in the list of status in db
                   if(in_array(strtolower($request->input($requiredParams[0])),$allStatus)){

                        // if matches, then update the record according
                       \DB::table('qa_testrun_result')
                           ->where('run_id', decryptData($request->input($requiredParams[3])))
                           ->where('test_run_id', decryptData($request->input($requiredParams[2])))
                           ->update(['status_id' => Statuses::where('statuses_name','=',preg_replace('/[-]/', ' ', strtolower($request->input($requiredParams[0]))))
                                                                ->first()
                                                                ->statuses_id,
                                    'status_change_date' => Carbon::now()
                                    ]);

                       // TRACKER
                       ApiTracker::trackRequest([
                           'clientID' => getClientFromDomain($request->header('domain')),
                           'projectID' => decryptData($request->input($requiredParams[1])),
                           'userID' => \Auth::user()->user_id,
                           'controller' =>  getControllerName($request->route()[1]['uses'])[0],
                           'method' => getControllerName($request->route()[1]['uses'])[1],
                           'status' => 'SUCCESS',
                           'route' => $request->method()
                       ]);

                       return response(['success' => true, 'msg' => "Updated Test Run's status to ". strtoupper($request->input($requiredParams[0]))]);

                   }
                   // TRACKER
                   ApiTracker::trackRequest([
                       'clientID' => getClientFromDomain($request->header('domain')),
                       'projectID' => decryptData($request->input($requiredParams[1])),
                       'userID' => \Auth::user()->user_id,
                       'controller' =>  getControllerName($request->route()[1]['uses'])[0],
                       'method' => getControllerName($request->route()[1]['uses'])[1],
                       'status' => 'Provided Status for update is not valid',
                       'route' => $request->method()
                   ]);
                    // if is does not match, thow error saying that status is wrong
                   return response(['success' => false, 'error_msg' => 'Provided Status is not Valid', 'available_status' => $allStatus]);

               }

                // TRACKER
                ApiTracker::trackRequest([
                    'clientID' => getClientFromDomain($request->header('domain')),
                    'projectID' => decryptData($request->input($requiredParams[1])),
                    'userID' => \Auth::user()->user_id,
                    'controller' =>  getControllerName($request->route()[1]['uses'])[0],
                    'method' => getControllerName($request->route()[1]['uses'])[1],
                    'status' => 'Wrong Test Run Result Key',
                    'route' => $request->method()
                ]);

               // if the test run key passed is not available then throw error
                return response(['success' => false, 'error_msg' => 'Test Run Result key not Found!']);


            }
            // TRACKER
            ApiTracker::trackRequest([
                'clientID' => getClientFromDomain($request->header('domain')),
                'projectID' => decryptData($request->input($requiredParams[1])),
                'userID' => \Auth::user()->user_id,
                'controller' =>  getControllerName($request->route()[1]['uses'])[0],
                'method' => getControllerName($request->route()[1]['uses'])[1],
                'status' => 'Wrong Test Run Key',
                'route' => $request->method()
            ]);


            // if the test run  id passed is not available then throw error
            return response(['success' => false, 'error_msg' => 'Test Run Key not found']);

        }

        // TRACKER
        ApiTracker::trackRequest([
            'clientID' => getClientFromDomain($request->header('domain')),
            'projectID' => $request->input($requiredParams[1]),
            'userID' => \Auth::user()->user_id,
            'controller' =>  getControllerName($request->route()[1]['uses'])[0],
            'method' => getControllerName($request->route()[1]['uses'])[1],
            'status' => 'Project Key not found',
            'route' => $request->method()
        ]);


        // if the project id passed is not available then throw error
        return response(['success' => false, 'error_msg' => 'Project Key not found']);

    }

    /*
     * function to get all the available users for the current project
     * @params
     *  1.projectID
     */
    public function getAllUsers(Request $request, $projectID){

        //get the page number from the request params
        $pageNo = $request->input('page');

        $allUsers = ProjectRole::join('qa_users', 'qa_project_userroles.user_id', '=', 'qa_users.id')
            ->selectRaw('qa_users.id as `user_key`,'.
                        'CONCAT(qa_users.firstname," ",qa_users.lastname) as `name`,'.
                        'qa_users.email as `email`')
            ->where('qa_project_userroles.role_id','>',0)
            ->where('qa_users.access_type','!=','developer')
            ->where('qa_users.client_id',getClientFromDomain($request->header('domain')))
            ->where('qa_project_userroles.project_id',decryptData($projectID))
            ->where('qa_users.status','=',1)
            ->get()
            ->chunk(50);

        if(count($allUsers) > 0){
            $allUsers = encArray($allUsers,'user_key');

            return getDataWithPageNo($allUsers,$pageNo);
        }
        else{
            return response(['success' => false, 'error_msg' => 'No User found for the Project'],200);

        }

    }
    /*
     *
     */
    public function createTestRun(Request $request){

        $params = checkParams($request->input(), ['projectKey','assignTo','milestoneKey','testRun'], getClientFromDomain($request->header('domain')), getControllerName($request->route()[1]['uses']), $request->method() );
        if($params != false){
            return response($params,400);
        }

        // check project key passed is correct or not
        if(checkProjectID($request->input('projectKey')) == false){

            //tracker
            ApiTracker::trackRequest([
                'clientID' => getClientFromDomain($request->header('domain')),
                'projectID' => $request->input('projectKey'),
                'userID' => \Auth::user()->user_id,
                'controller' =>  getControllerName($request->route()[1]['uses'])[0],
                'method' => getControllerName($request->route()[1]['uses'])[1],
                'status' => 'Project Key not Found!',
                'route' => $request->method()
            ]);

            return response(['success' => false, 'error_msg' => 'Project Key not Found!'],404);

        }

        // check milestone key passed is correct or not
        if(checkMilestoneID($request->input('milestoneKey')) == false){

            //tracker
            ApiTracker::trackRequest([
                'clientID' => getClientFromDomain($request->header('domain')),
                'projectID' => $request->input('projectKey'),
                'userID' => \Auth::user()->user_id,
                'controller' =>  getControllerName($request->route()[1]['uses'])[0],
                'method' => getControllerName($request->route()[1]['uses'])[1],
                'status' => 'Milestone Key not Found!',
                'route' => $request->method()
            ]);

            return response(['success' => false, 'error_msg' => 'Milestone Key not Found!'],404);

        }

        if(!$this->checkUserForProject(getClientFromDomain($request->header('domain')), decryptData($request->input('projectKey')), decryptData($request->input('assignTo')))){

            //tracker
            ApiTracker::trackRequest([
                'clientID' => getClientFromDomain($request->header('domain')),
                'projectID' => $request->input('projectKey'),
                'userID' => \Auth::user()->user_id,
                'controller' =>  getControllerName($request->route()[1]['uses'])[0],
                'method' => getControllerName($request->route()[1]['uses'])[1],
                'status' => 'User Key not Found For Project!',
                'route' => $request->method()
            ]);

            return response(['success' => false, 'error_msg' => 'User Key not Found For Project!'],404);

        }

        $testRunData = [
            'client_id' => getClientFromDomain($request->header('domain')), 'project_id' => decryptData($request->input('projectKey')), 'user_id' => \Auth::user()->user_id,
            'milestone_id' =>  decryptData($request->input('milestoneKey')), 'parent_id' => 0, 'assign_user_id' => decryptData($request->input('assignTo')),
            'name' => $request->input('testRun'), 'description' => '', 'radio_testcases' => 'all',
            'status' => 1, 'created_at' => Carbon::now(), 'created_time' => Carbon::now()
        ];

        $testRun = \DB::table('qa_testrun')
                ->insertGetId($testRunData);

        $projectid = decryptData($request->input('projectKey'));
        $assignuser = decryptData($request->input('assignTo'));

        $casedetail = Cases::select(['case_id', 'section_id'])
            ->where('project_id', '=', "$projectid")
            ->where('status',1)
            ->get();

        foreach ($casedetail as $caselist) {
            $testrunresultAll = new TestRunResult;
            $testrunresultAll->project_id = $projectid;
            $testrunresultAll->test_run_id = $testRun;
            $testrunresultAll->section_id = $caselist->section_id;
            $testrunresultAll->case_id = $caselist->case_id;
            $testrunresultAll->status_id = 2;
            $testrunresultAll->assign_user = $assignuser;
            $testrunresultAll->comments = '';
            $testrunresultAll->image = '';
            $testrunresultAll->created_time = Carbon::now();
            $testrunresultAll->save();

         }

        //encryptData($releaseID);
        $testRunData = array('milestone_id' => encryptData($testRun)) + $testRunData;
        // change from id to key
        $testRunData['project_id'] = $request->input('projectKey');

        // send response back to client
        return response(['success' => true, 'data' => [$testRunData]],200);

    }

    public function checkUserForProject($_clientID, $_projectID, $_userID){

        $allUsers = ProjectRole::join('qa_users', 'qa_project_userroles.user_id', '=', 'qa_users.id')
            ->selectRaw('qa_users.id as `user_key`,'.
                'CONCAT(qa_users.firstname," ",qa_users.lastname) as `name`,'.
                'qa_users.email as `email`')
            ->where('qa_project_userroles.role_id','>',0)
            ->where('qa_users.access_type','!=','developer')
            ->where('qa_users.client_id', $_clientID)
            ->where('qa_project_userroles.project_id', $_projectID)
            ->where('qa_users.id','=', $_userID)
            ->where('qa_users.status','=',1)
            ->get()
            ->count();

        return ($allUsers > 0) ? true : false;


    }

}
