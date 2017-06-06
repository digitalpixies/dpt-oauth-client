angular
  .module('DPTOAuthClientApp', [
    'ngAnimate',
    'ngCookies',
//    'ngResource',
//    'ngRoute',
    'ngSanitize',
    'ngTouch',
    'ui.bootstrap',
    'ngFileUpload'
  ]);

angular.module('DPTOAuthClientApp')
  .controller('ProfileCtrl', function ($scope, $http, $httpParamSerializerJQLike, $uibModal, $document) {
//    console.log(wordpress);
    $scope.control={};
    $scope.control.has_auth_code=false;
    $scope.Unlink=function() {
      $http({
        method: "POST",
        url: wordpress.ajax_url,
        data: $httpParamSerializerJQLike({action:"dpt-oauth-ajax",call:"unlink_auth_code"}),
        headers: {'Content-Type': 'application/x-www-form-urlencoded'}
      }).success(function(response) {
        $scope.control.has_auth_code=false;
      });
    };
    $scope.control.modal={instance:null};
    $scope.Invoke=function() {
      $http({
        method: "POST",
        url: wordpress.ajax_url,
        data: $httpParamSerializerJQLike({action:"dpt-oauth-ajax",call:"invoke"}),
        headers: {'Content-Type': 'application/x-www-form-urlencoded'}
      }).success(function(response) {
        $scope.control.modal.instance=$uibModal.open({
          ariaLabelledBy: 'modal-title',
          ariaDescribedBy: 'modal-body',
          templateUrl: 'OAuthResourceResponseModal.html',
          appendTo: angular.element($document[0].querySelector('#OAuthResourceResponseModal')),
          controller: function($scope) {
            $scope.entries=response.response;
            $scope.control={};
            $scope.control.modal={};
            $scope.control.modal.OK=function() {
              $scope.$close();
            };
          }
        });
      });
    };
  })
  .controller('AdminCtrl', function ($scope, $http, $httpParamSerializerJQLike) {
    $scope.control={};
    $scope.control.custom={value:1};
    $scope.control.custom.Change=function() {
      switch($scope.control.custom.value) {
        case 1:
          $scope.control.label.disabled=false;
          $scope.control.auth_url.disabled=false;
          $scope.control.auth_querystring.disabled=false;
          $scope.control.token_url.disabled=false;
          $scope.control.scope.disabled=false;
          $scope.control.resource_url.disabled=false;
          $scope.control.resource_querystring.disabled=false;
          break;
        case 0:
          switch($scope.control.preset.value) {
            case "google":
              $scope.control.label.disabled=true;
              $scope.control.label.value="Google";
              $("#client_label").attr('value',$scope.control.label.value);
              $scope.control.auth_url.disabled=true;
              $scope.control.auth_url.value="https://accounts.google.com/o/oauth2/v2/auth";
              $("#client_auth_url").attr('value',$scope.control.auth_url.value);
              $scope.control.auth_querystring.disabled=true;
              $scope.control.auth_querystring.value="access_type=offline&prompt=consent%20select_account";
              $("#client_auth_querystring").attr('value',$scope.control.auth_querystring.value);
              $scope.control.token_url.disabled=true;
              $scope.control.token_url.value="https://www.googleapis.com/oauth2/v4/token";
              $("#client_token_url").attr('value',$scope.control.token_url.value);
              $scope.control.scope.disabled=true;
              $scope.control.scope.value="profile";
              $("#client_scope").attr('value',$scope.control.scope.value);
              $scope.control.resource_url.disabled=true;
              $scope.control.resource_url.value="https://www.googleapis.com/oauth2/v1/userinfo";
              $("#client_resource_url").attr('value',$scope.control.resource_url.value);
              $scope.control.resource_querystring.disabled=true;
              $scope.control.resource_querystring.value="alt=json";
              $("#client_resource_querystring").attr('value',$scope.control.resource_querystring.value);
              break;
          }
          break;
      }
      console.log("Change called");
    };
    $scope.control.preset={value:"google"};
    $scope.control.label={disabled:false, value:""};
    $scope.control.scope={disabled:false, value:""};
    $scope.control.auth_url={disabled:false, value:""};
    $scope.control.auth_querystring={disabled:false, value:""};
    $scope.control.token_url={disabled:false, value:""};
    $scope.control.resource_url={disabled:false, value:""};
    $scope.control.resource_querystring={disabled:false, value:""};
  });
